<?php

namespace App\Listeners;

use App\Events\FormSubmissionCreated;
use App\Jobs\SyncSubmissionToSheet;
use App\Models\FormSheetLink;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class QueueSheetSync implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the submission event after database transaction has committed.
     */
    public function handle(FormSubmissionCreated $event): void
    {
        $submission = $event->submission;
        if (!$submission || !$submission->form_id) {
            return;
        }

        // Check if this form has an active Google Sheet link with sync enabled
        $link = FormSheetLink::where('form_id', $submission->form_id)
            ->where('sync_enabled', true)
            ->first();

        if ($link) {
            SyncSubmissionToSheet::dispatch($submission->id);
        }
    }
}
