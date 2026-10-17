<?php

namespace App\Jobs;

use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\SheetSyncRecord;
use App\Services\GoogleSheets\SheetsGateway;
use App\Services\GoogleSheets\SubmissionRowBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSubmissionToSheet implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** The number of times the job may be attempted. */
    public int $tries = 4;

    /** Exponential backoff in seconds between attempts: 10s, 1m, 5m, 15m. */
    public array $backoff = [10, 60, 300, 900];

    /** Delete the job if its models no longer exist. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public int $submissionId)
    {
        $this->onQueue('sheets');
    }

    /** The unique ID of the job. */
    public function uniqueId(): string
    {
        return (string) $this->submissionId;
    }

    public function handle(SheetsGateway $gateway, SubmissionRowBuilder $rowBuilder): void
    {
        $submission = FormSubmission::with(['cosplayer.user', 'values', 'form'])->find($this->submissionId);
        if (!$submission || !$submission->form) {
            return;
        }

        $link = FormSheetLink::with(['connection', 'activeMappings'])
            ->where('form_id', $submission->form_id)
            ->first();

        if (!$link || !$link->sync_enabled || !$link->connection || !$link->connection->isConnected()) {
            return;
        }

        $record = SheetSyncRecord::firstOrCreate(
            [
                'form_submission_id' => $submission->id,
                'form_sheet_link_id' => $link->id,
            ],
            [
                'status'   => 'pending',
                'attempts' => 0,
            ]
        );

        $record->increment('attempts');

        try {
            $row = $rowBuilder->buildRow($submission, $link);
            if (empty($row)) {
                return;
            }

            $hash = $rowBuilder->computeHash($row);

            // Skip if already successfully synced with the exact same payload
            if ($record->status === 'synced' && $record->payload_hash === $hash) {
                return;
            }

            // Append row to the sheet
            $response = $gateway->appendRows(
                $link->connection,
                $link->spreadsheet_id,
                $link->sheet_title,
                [$row]
            );

            // Parse row number from updatedRange (e.g. "'Responses'!A42:H42" -> 42)
            $parsedRowNumber = null;
            if (!empty($response['updatedRange'])) {
                if (preg_match('/!.*?[A-Z]+(\d+):/i', $response['updatedRange'], $matches)) {
                    $parsedRowNumber = (int) $matches[1];
                }
            }

            $record->update([
                'status'       => 'synced',
                'sheet_row'    => $parsedRowNumber ?? $record->sheet_row,
                'synced_at'    => now(),
                'payload_hash' => $hash,
                'last_error'   => null,
            ]);

            $link->update([
                'last_synced_at' => now(),
                'last_error'     => null,
            ]);

            Log::info("Synced submission {$submission->id} to Google Sheet '{$link->spreadsheet_title}' (row: {$parsedRowNumber})");

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("Failed to sync submission {$submission->id} to Google Sheet: {$errorMsg}");

            $record->update([
                'status'     => 'failed',
                'last_error' => $errorMsg,
            ]);

            $link->update([
                'last_error' => $errorMsg,
            ]);

            // If error is invalid_grant, connection was revoked
            if (str_contains($errorMsg, 'invalid_grant')) {
                $link->update(['status' => 'disconnected']);
            }

            // Rethrow so queue backoff and retries are triggered
            throw $e;
        }
    }
}
