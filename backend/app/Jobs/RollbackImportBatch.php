<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RollbackImportBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $batchId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $batch = ImportBatch::find($this->batchId);
        if (!$batch || $batch->status !== 'completed') {
            return;
        }

        // 24-hour safety window check
        if ($batch->finished_at && $batch->finished_at->lt(now()->subHours(24))) {
            throw new \RuntimeException('Cannot rollback an import batch older than 24 hours.');
        }

        DB::transaction(function () use ($batch) {
            // Find and delete submissions created by this batch
            FormSubmission::where('import_batch_id', $batch->id)->delete();

            // Reset rows in the batch
            $batch->rows()->update([
                'status'             => 'valid',
                'form_submission_id' => null,
            ]);

            $batch->update([
                'status'        => 'rolled_back',
                'imported_rows' => 0,
            ]);
        });

        Log::info("Rollback completed for import batch {$batch->id}");
    }
}
