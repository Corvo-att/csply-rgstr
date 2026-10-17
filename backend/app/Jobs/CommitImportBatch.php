<?php

namespace App\Jobs;

use App\Models\FormSheetLink;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\SheetSyncRecord;
use App\Services\GoogleSheets\SheetsGateway;
use App\Services\GoogleSheets\SubmissionRowBuilder;
use App\Services\Import\AccountResolver;
use App\Services\SubmissionWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommitImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $batchId)
    {
        $this->onQueue('default');
    }

    public function handle(
        AccountResolver $accountResolver,
        SubmissionWriter $writer,
        SheetsGateway $gateway,
        SubmissionRowBuilder $rowBuilder,
    ): void {
        $batch = ImportBatch::with('form')->find($this->batchId);
        if (!$batch || $batch->status === 'completed') {
            return;
        }

        $batch->update([
            'status'     => 'importing',
            'started_at' => now(),
        ]);

        $duplicateStrategy = $batch->options['duplicate_strategy'] ?? 'skip';
        $allowUpdate = ($duplicateStrategy === 'update');

        $link = FormSheetLink::with(['connection', 'activeMappings'])
            ->where('form_id', $batch->form_id)
            ->first();

        $sheetActive = ($link && $link->sync_enabled && $link->connection && $link->connection->isConnected());

        $importedCount = 0;
        $createdSubmissions = [];

        // Fetch all rows marked valid
        $validRows = $batch->rows()
            ->where('status', 'valid')
            ->orderBy('row_number')
            ->get();

        // Process in 100-row chunks for atomic transactions and memory efficiency
        foreach ($validRows->chunk(100) as $chunk) {
            DB::transaction(function () use ($chunk, $batch, $accountResolver, $writer, $allowUpdate, &$importedCount, &$createdSubmissions) {
                foreach ($chunk as $row) {
                    $norm = $row->normalized ?? [];
                    $identity = $norm['identity'] ?? [];
                    $values = $norm['values'] ?? [];

                    try {
                        $cosplayer = $accountResolver->resolve($identity);

                        // Don't dispatch individual sheet sync jobs now; we batch sync to Google Sheets below
                        $submission = $writer->writeOrUpdate(
                            form: $batch->form,
                            cosplayer: $cosplayer,
                            values: $values,
                            source: 'import',
                            importBatchId: $batch->id,
                            dispatchEvent: false,
                            allowUpdate: $allowUpdate,
                        );

                        $row->update([
                            'status'             => 'imported',
                            'form_submission_id' => $submission->id,
                        ]);

                        $importedCount++;
                        $createdSubmissions[] = $submission;

                    } catch (\Throwable $e) {
                        Log::error("Failed to import row {$row->row_number} in batch {$batch->id}: " . $e->getMessage());
                        $row->update([
                            'status' => 'invalid',
                            'errors' => ['system' => [$e->getMessage()]],
                        ]);
                    }
                }
            });
        }

        // ── Batch append to Google Sheets if connected and enabled ──────
        if ($sheetActive && !empty($createdSubmissions)) {
            try {
                // Chunk into 200-row spreadsheet appends
                foreach (array_chunk($createdSubmissions, 200) as $subChunk) {
                    $rowsToAppend = [];
                    $recordsToCreate = [];

                    foreach ($subChunk as $sub) {
                        $sub->loadMissing(['cosplayer.user', 'values', 'form']);
                        $sheetRow = $rowBuilder->buildRow($sub, $link);
                        if (empty($sheetRow)) {
                            continue;
                        }

                        $hash = $rowBuilder->computeHash($sheetRow);
                        $rowsToAppend[] = $sheetRow;
                        $recordsToCreate[] = [
                            'submission_id' => $sub->id,
                            'hash'          => $hash,
                        ];
                    }

                    if (!empty($rowsToAppend)) {
                        $gateway->appendRows(
                            $link->connection,
                            $link->spreadsheet_id,
                            $link->sheet_title,
                            $rowsToAppend
                        );

                        foreach ($recordsToCreate as $rec) {
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
                    }
                }

                $link->update(['last_synced_at' => now(), 'last_error' => null]);
                Log::info("Synced " . count($createdSubmissions) . " imported rows to Google Sheet '{$link->spreadsheet_title}'");

            } catch (\Throwable $e) {
                Log::error("Batch Google Sheet sync failed for import batch {$batch->id}: " . $e->getMessage());
                // Fallback: Individual submissions can still be reconciled later via sheets:reconcile
                foreach ($createdSubmissions as $sub) {
                    SheetSyncRecord::updateOrCreate(
                        [
                            'form_submission_id' => $sub->id,
                            'form_sheet_link_id' => $link->id,
                        ],
                        [
                            'status'     => 'failed',
                            'last_error' => $e->getMessage(),
                        ]
                    );
                }
            }
        }

        $batch->update([
            'status'        => 'completed',
            'imported_rows' => $importedCount,
            'finished_at'   => now(),
        ]);

        Log::info("Import batch {$batch->id} successfully completed: {$importedCount} rows imported.");
    }
}
