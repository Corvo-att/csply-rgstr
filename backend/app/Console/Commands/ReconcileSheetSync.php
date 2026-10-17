<?php

namespace App\Console\Commands;

use App\Jobs\SyncSubmissionToSheet;
use App\Models\SheetSyncRecord;
use Illuminate\Console\Command;

class ReconcileSheetSync extends Command
{
    protected $signature = 'sheets:reconcile';
    protected $description = 'Reconcile and retry pending or failed Google Sheets submission syncs.';

    public function handle(): int
    {
        $this->info('Checking for unsynced or failed Google Sheet submissions...');

        $stuckRecords = SheetSyncRecord::with('link')
            ->where(function ($q) {
                $q->where('status', 'failed')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'pending')
                         ->where('created_at', '<=', now()->subMinutes(5));
                  });
            })
            ->where('attempts', '<', 10)
            ->whereHas('link', function ($q) {
                $q->where('sync_enabled', true)->where('status', '!=', 'disconnected');
            })
            ->limit(100)
            ->get();

        if ($stuckRecords->isEmpty()) {
            $this->info('All submissions are cleanly synchronized.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($stuckRecords as $rec) {
            SyncSubmissionToSheet::dispatch($rec->form_submission_id);
            $count++;
        }

        $this->info("Dispatched {$count} submission(s) for Google Sheets reconciliation.");
        return Command::SUCCESS;
    }
}
