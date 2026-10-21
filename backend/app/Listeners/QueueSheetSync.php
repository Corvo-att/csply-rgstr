<?php

namespace App\Listeners;

use App\Events\FormSubmissionCreated;
use App\Jobs\FlushSheetLink;
use App\Services\GoogleSheets\SheetSyncDispatcher;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class QueueSheetSync implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly SheetSyncDispatcher $sync) {}

    /**
     * Handle the submission event after database transaction has committed.
     * Costs one tiny query: the real work happens later, batched, in FlushSheetLink.
     */
    public function handle(FormSubmissionCreated $event): void
    {
        $submission = $event->submission;
        if (! $submission || ! $submission->form_id) {
            return;
        }

        $this->sync->changed($submission);
    }
}
