<?php

namespace App\Jobs;

use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\SheetSyncRecord;
use App\Services\GoogleSheets\SheetsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Brings a sheet up to date by flagging submissions as pending and letting FlushSheetLink
 * do the (batched, duplicate-free) writing.
 *
 *   missing - only submissions that were never pushed successfully
 *   verify  - every submission; unchanged rows are skipped, missing/changed rows are repaired
 *   rebuild - DESTRUCTIVE: wipes the data rows in the sheet first, then writes everything again
 */
class BackfillFormToSheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MODES = ['missing', 'verify', 'rebuild'];

    public int $timeout = 600;

    public function __construct(
        public int $formId,
        public string $mode = 'missing',
    ) {
        $this->onQueue('sheets');
    }

    public function handle(SheetsGateway $gateway): void
    {
        $link = FormSheetLink::with('connection')->where('form_id', $this->formId)->first();

        if (! $link || ! $link->connection?->isConnected()) {
            return;
        }

        if ($this->mode === 'rebuild') {
            $gateway->clearDataRows($link->connection, $link->spreadsheet_id, $link->sheet_title, $link->header_row + 1);
            SheetSyncRecord::where('form_sheet_link_id', $link->id)->delete();
        }

        $query = FormSubmission::where('form_id', $this->formId)->select('id');
        if ($this->mode === 'missing') {
            $query->whereDoesntHave('sheetSyncRecords', fn ($q) => $q->where('form_sheet_link_id', $link->id)->where('status', 'synced'));
        }

        $total = 0;
        $query->chunkById(1000, function ($subs) use ($link, &$total) {
            $now = now();
            SheetSyncRecord::upsert(
                $subs->map(fn ($s) => [
                    'form_submission_id' => $s->id,
                    'form_sheet_link_id' => $link->id,
                    'status'             => 'pending',
                    'attempts'           => 0,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ])->all(),
                ['form_submission_id', 'form_sheet_link_id'],
                ['status', 'attempts', 'updated_at'],
            );
            $total += $subs->count();
        });

        Log::info("Sheet {$this->mode} for form {$this->formId}: {$total} submission(s) queued");

        if ($total > 0) {
            FlushSheetLink::dispatch($link->id);
        }
    }
}
