<?php

namespace App\Jobs;

use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\SheetColumnMapping;
use App\Models\SheetPendingDelete;
use App\Models\SheetSyncRecord;
use App\Services\GoogleSheets\SheetsGateway;
use App\Services\GoogleSheets\SubmissionRowBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Drains every pending submission of ONE sheet link in as few Google calls as possible:
 *   1 read  (the Submission ID column, so rows are found by id - sorting the sheet is safe)
 *   1 write (all edited rows, only the mapped columns)
 *   1 append (all new rows)
 *   1 write (rows of deleted submissions get "Deleted" in the Status column)
 *
 * Writers only flip a flag in the database; this job is debounced so a burst of 500
 * registrations becomes a handful of API calls instead of 500.
 */
class FlushSheetLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600, 1800];

    private const CHUNK = 200;

    private const MAX_ATTEMPTS = 10;

    public function __construct(public int $linkId)
    {
        $this->onQueue('sheets');
    }

    /** Schedule one flush shortly from now; further calls inside the window do nothing. */
    public static function dispatchDebounced(int $linkId, int $delaySeconds = 15): void
    {
        if (Cache::add(self::queuedKey($linkId), 1, $delaySeconds + 60)) {
            static::dispatch($linkId)->delay(now()->addSeconds($delaySeconds));
        }
    }

    private static function queuedKey(int $linkId): string
    {
        return "sheet-flush-queued:{$linkId}";
    }

    public function handle(SheetsGateway $gateway, SubmissionRowBuilder $builder): void
    {
        // Allow the next burst to schedule a new flush while this one runs.
        Cache::forget(self::queuedKey($this->linkId));

        $link = FormSheetLink::with(['connection', 'activeMappings'])->find($this->linkId);
        if (! $link || ! $link->sync_enabled || $link->status === 'disconnected' || ! $link->connection?->isConnected()) {
            return;
        }
        if ($link->activeMappings->isEmpty()) {
            return;
        }

        $lock = Cache::lock("sheet-flush:{$link->id}", 600);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $this->flush($link, $gateway, $builder);
        } finally {
            $lock->release();
        }
    }

    private function flush(FormSheetLink $link, SheetsGateway $gateway, SubmissionRowBuilder $builder): void
    {
        $mappings = $link->activeMappings;
        $conn = $link->connection;
        $writableColumns = $mappings->pluck('column_index')->all();
        $idMapping = $mappings->first(fn ($m) => $m->source_type === 'system' && in_array($m->source_key, ['submission_id', 'uuid', 'entry_number'], true));
        $statusMapping = $mappings->first(fn ($m) => $m->source_type === 'system' && $m->source_key === 'status');

        $pendingQuery = SheetSyncRecord::where('form_sheet_link_id', $link->id)
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', self::MAX_ATTEMPTS);
        $deletes = SheetPendingDelete::where('form_sheet_link_id', $link->id)->get();

        if (! (clone $pendingQuery)->exists() && $deletes->isEmpty()) {
            return;
        }

        try {
            // One cheap read tells us where every submission currently lives in the sheet.
            $idToRow = $idMapping ? $this->loadRowMap($gateway, $link, $idMapping) : null;

            $failures = 0;
            $nextAppendRow = $idToRow !== null ? (empty($idToRow) ? $link->header_row + 1 : max($idToRow) + 1) : null;

            (clone $pendingQuery)->chunkById(self::CHUNK, function ($records) use (
                $gateway, $builder, $link, $conn, $writableColumns, $idMapping, $idToRow, &$nextAppendRow, &$failures
            ) {
                $subs = FormSubmission::with(['cosplayer.user', 'values'])
                    ->whereIn('id', $records->pluck('form_submission_id'))
                    ->get()->keyBy('id');

                $appends = [];   // list of [record, row, hash]
                $updates = [];   // rowNumber => row
                $updateRecs = []; // rowNumber => [record, hash]
                $unchanged = [];

                foreach ($records as $rec) {
                    $sub = $subs->get($rec->form_submission_id);
                    if (! $sub) {
                        $rec->delete();

                        continue;
                    }

                    $row = $builder->buildRow($sub, $link);
                    if (empty($row)) {
                        continue;
                    }
                    $hash = $builder->computeHash($row);

                    $idKey = match ($idMapping?->source_key) {
                        'uuid' => (string) $sub->uuid,
                        'entry_number' => (string) $sub->entry_number,
                        default => (string) $sub->id,
                    };

                    $rowNumber = $idToRow !== null
                        ? ($idToRow[$idKey] ?? null)
                        : ($rec->synced_at ? $rec->sheet_row : null);

                    if ($rowNumber && $rec->synced_at && $rec->payload_hash === $hash) {
                        $unchanged[] = [$rec, $rowNumber, $hash];
                    } elseif ($rowNumber) {
                        $updates[$rowNumber] = $row;
                        $updateRecs[$rowNumber] = [$rec, $hash];
                    } else {
                        $appends[] = [$rec, $row, $hash];
                    }
                }

                try {
                    if ($updates) {
                        $gateway->batchUpdateRows($conn, $link->spreadsheet_id, $link->sheet_title, $updates, $writableColumns);
                        foreach ($updateRecs as $rowNumber => [$rec, $hash]) {
                            $this->markSynced($rec, $rowNumber, $hash);
                        }
                    }

                    if ($appends) {
                        $response = $gateway->appendRows($conn, $link->spreadsheet_id, $link->sheet_title, array_column($appends, 1));
                        $start = $this->parseStartRow($response['updatedRange'] ?? '') ?? $nextAppendRow;

                        foreach ($appends as $i => [$rec, , $hash]) {
                            $this->markSynced($rec, $start !== null ? $start + $i : null, $hash);
                        }
                        if ($start !== null) {
                            $nextAppendRow = $start + count($appends);
                        }
                    }

                    foreach ($unchanged as [$rec, $rowNumber, $hash]) {
                        $this->markSynced($rec, $rowNumber, $hash);
                    }
                } catch (\Throwable $e) {
                    if ($this->isRetryable($e) || $this->isRevoked($e, $link)) {
                        throw $e; // handled by the outer catch: records stay pending, job is retried
                    }

                    $failures += $this->markFailed(
                        collect($updateRecs)->map(fn ($p) => $p[0])->merge(collect($appends)->map(fn ($p) => $p[0])),
                        $e
                    );
                }
            });

            $this->markDeletedRows($gateway, $link, $deletes, $idToRow, $statusMapping, $idMapping);

            $link->update(['last_synced_at' => now(), 'last_error' => $failures ? $link->last_error : null]);
        } catch (\Throwable $e) {
            if ($this->isRevoked($e, $link)) {
                return;
            }

            $link->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

            if ($this->isRetryable($e)) {
                Log::warning("Sheet link {$link->id}: Google asked us to slow down, retrying. {$e->getMessage()}");
                $this->release(60);

                return;
            }

            Log::error("Sheet link {$link->id}: flush failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /** @return array<string, int> submission id => sheet row number */
    private function loadRowMap(SheetsGateway $gateway, FormSheetLink $link, SheetColumnMapping $idMapping): array
    {
        $start = $link->header_row + 1;
        $cells = $gateway->readColumn(
            $link->connection,
            $link->spreadsheet_id,
            $link->sheet_title,
            SheetColumnMapping::indexToLetter($idMapping->column_index),
            $start,
        );

        $map = [];
        foreach ($cells as $i => $value) {
            $value = trim((string) $value);
            if ($value !== '' && ! isset($map[$value])) {
                $map[$value] = $start + $i;   // first occurrence wins
            }
        }

        return $map;
    }

    private function markDeletedRows(SheetsGateway $gateway, FormSheetLink $link, $deletes, ?array $idToRow, ?SheetColumnMapping $statusMapping, ?SheetColumnMapping $idMapping): void
    {
        if ($deletes->isEmpty()) {
            return;
        }

        $rows = [];
        if ($statusMapping && $idToRow !== null) {
            foreach ($deletes as $d) {
                $delKey = match ($idMapping?->source_key) {
                    'entry_number' => (string) $d->entry_number,
                    default => (string) $d->submission_id,
                };
                if ($row = $idToRow[$delKey] ?? null) {
                    $rows[$row] = [$statusMapping->column_index => 'Deleted'];
                }
            }
        }

        if ($rows) {
            $gateway->batchUpdateRows($link->connection, $link->spreadsheet_id, $link->sheet_title, $rows, [$statusMapping->column_index]);
        } elseif (! $statusMapping) {
            Log::info("Sheet link {$link->id}: no Status column mapped, deleted entries cannot be marked in the sheet.");
        }

        SheetPendingDelete::whereIn('id', $deletes->pluck('id'))->delete();
    }

    private function markSynced(SheetSyncRecord $rec, ?int $row, string $hash): void
    {
        $rec->update([
            'status'       => 'synced',
            'sheet_row'    => $row ?? $rec->sheet_row,
            'synced_at'    => now(),
            'payload_hash' => $hash,
            'last_error'   => null,
        ]);
    }

    private function markFailed($records, \Throwable $e): int
    {
        foreach ($records as $rec) {
            $rec->update([
                'status'     => 'failed',
                'attempts'   => $rec->attempts + 1,
                'last_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }

        return $records->count();
    }

    private function parseStartRow(string $updatedRange): ?int
    {
        return preg_match('/!\$?[A-Z]+\$?(\d+)/i', $updatedRange, $m) ? (int) $m[1] : null;
    }

    /** Quota / temporary Google errors: keep records pending and try again later. */
    private function isRetryable(\Throwable $e): bool
    {
        return in_array((int) $e->getCode(), [429, 500, 502, 503, 504], true)
            || str_contains(strtolower($e->getMessage()), 'rate limit')
            || str_contains(strtolower($e->getMessage()), 'quota');
    }

    private function isRevoked(\Throwable $e, FormSheetLink $link): bool
    {
        if (str_contains($e->getMessage(), 'invalid_grant')) {
            $link->update(['status' => 'disconnected', 'last_error' => 'Google access was revoked. Reconnect your Google account.']);

            return true;
        }

        return false;
    }
}
