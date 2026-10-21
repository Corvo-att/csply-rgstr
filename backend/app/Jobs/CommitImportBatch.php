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

        $importedCount = 0;

        // Process in 100-row chunks for atomic transactions and memory efficiency
        $batch->rows()
            ->where('status', 'valid')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($batch, $accountResolver, $writer, $allowUpdate, &$importedCount) {
                DB::transaction(function () use ($chunk, $batch, $accountResolver, $writer, $allowUpdate, &$importedCount) {
                    foreach ($chunk as $row) {
                        $norm = $row->normalized ?? [];
                        $identity = $norm['identity'] ?? [];
                        $values = $norm['values'] ?? [];

                        try {
                            $cosplayer = $accountResolver->resolve($identity);

                            // SubmissionWriter marks the sync record pending without dispatching per-row jobs
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

                        } catch (\Throwable $e) {
                            Log::error("Failed to import row {$row->row_number} in batch {$batch->id}: " . $e->getMessage());
                            $row->update([
                                'status' => 'invalid',
                                'errors' => ['system' => [$e->getMessage()]],
                            ]);
                        }
                    }
                });
            });

        // ── Single batched sheet flush at the end ───────────────────────
        $link = FormSheetLink::where('form_id', $batch->form_id)->where('sync_enabled', true)->first();
        if ($link) {
            FlushSheetLink::dispatch($link->id);
            Log::info("Dispatched FlushSheetLink for form {$batch->form_id} after import batch {$batch->id}");
        }

        $batch->update([
            'status'        => 'completed',
            'imported_rows' => $importedCount,
            'finished_at'   => now(),
        ]);

        Log::info("Import batch {$batch->id} successfully completed: {$importedCount} rows imported.");
    }
}
