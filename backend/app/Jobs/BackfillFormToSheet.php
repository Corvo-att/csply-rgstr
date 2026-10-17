<?php

namespace App\Jobs;

use App\Models\Form;
use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\SheetSyncRecord;
use App\Services\GoogleSheets\SheetsGateway;
use App\Services\GoogleSheets\SubmissionRowBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackfillFormToSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public int $formId,
        public bool $syncAll = false, // false = only unsynced, true = force resync all
    ) {
        $this->onQueue('sheets');
    }

    public function handle(SheetsGateway $gateway, SubmissionRowBuilder $rowBuilder): void
    {
        $link = FormSheetLink::with(['connection', 'activeMappings'])
            ->where('form_id', $this->formId)
            ->first();

        if (!$link || !$link->connection || !$link->connection->isConnected()) {
            return;
        }

        $query = FormSubmission::with(['cosplayer.user', 'values'])
            ->where('form_id', $this->formId)
            ->orderBy('id', 'asc');

        if (!$this->syncAll) {
            // Filter only submissions without a successful sync record
            $query->whereDoesntHave('sheetSyncRecords', function ($q) use ($link) {
                $q->where('form_sheet_link_id', $link->id)->where('status', 'synced');
            });
        }

        $total = $query->count();
        if ($total === 0) {
            Log::info("No submissions to backfill for form {$this->formId}");
            return;
        }

        Log::info("Starting backfill of {$total} submissions for form {$this->formId}");

        $query->chunk(200, function ($submissions) use ($gateway, $rowBuilder, $link) {
            $rowsToAppend = [];
            $recordsToUpdate = [];

            foreach ($submissions as $sub) {
                $row = $rowBuilder->buildRow($sub, $link);
                if (empty($row)) {
                    continue;
                }

                $hash = $rowBuilder->computeHash($row);
                $rowsToAppend[] = $row;
                $recordsToUpdate[] = [
                    'submission_id' => $sub->id,
                    'hash'          => $hash,
                ];
            }

            if (!empty($rowsToAppend)) {
                try {
                    $gateway->appendRows(
                        $link->connection,
                        $link->spreadsheet_id,
                        $link->sheet_title,
                        $rowsToAppend
                    );

                    foreach ($recordsToUpdate as $rec) {
                        SheetSyncRecord::updateOrCreate(
                            [
                                'form_submission_id' => $rec['submission_id'],
                                'form_sheet_link_id' => $link->id,
                            ],
                            [
                                'status'       => 'synced',
                                'synced_at'    => now(),
                                'payload_hash' => $rec['hash'],
                                'last_error'   => null,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::error("Backfill batch failed for form {$this->formId}: " . $e->getMessage());
                    throw $e;
                }
            }
        });

        $link->update(['last_synced_at' => now()]);
        Log::info("Backfill completed for form {$this->formId}");
    }
}
