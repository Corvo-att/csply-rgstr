<?php

namespace App\Console\Commands;

use App\Jobs\FlushSheetLink;
use App\Models\SheetPendingDelete;
use App\Models\SheetSyncRecord;
use Illuminate\Console\Command;

class ReconcileSheetSync extends Command
{
    protected $signature = 'sheets:reconcile';
    protected $description = 'Re-run the Google Sheets flush for every sheet that still has pending, failed or deleted entries.';

    public function handle(): int
    {
        $linkIds = SheetSyncRecord::query()
            ->where(function ($q) {
                $q->where(fn ($f) => $f->where('status', 'failed')->where('attempts', '<', 10))
                  ->orWhere(fn ($p) => $p->where('status', 'pending')->where('updated_at', '<=', now()->subMinutes(2)));
            })
            ->whereHas('link', fn ($q) => $q->where('sync_enabled', true)->where('status', '!=', 'disconnected'))
            ->distinct()
            ->pluck('form_sheet_link_id')
            ->merge(SheetPendingDelete::query()->distinct()->pluck('form_sheet_link_id'))
            ->unique();

        if ($linkIds->isEmpty()) {
            $this->info('All sheets are in sync.');

            return Command::SUCCESS;
        }

        // One job per SHEET (not per submission); the job locks itself so overlaps are harmless.
        foreach ($linkIds as $id) {
            FlushSheetLink::dispatch($id);
        }

        $this->info("Dispatched {$linkIds->count()} sheet flush job(s).");

        return Command::SUCCESS;
    }
}
