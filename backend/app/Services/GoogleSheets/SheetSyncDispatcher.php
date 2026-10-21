<?php

namespace App\Services\GoogleSheets;

use App\Jobs\FlushSheetLink;
use App\Models\FormSheetLink;
use App\Models\FormSubmission;
use App\Models\SheetPendingDelete;
use App\Models\SheetSyncRecord;

/**
 * The ONLY place that decides "this submission must (re)appear in the sheet".
 *
 * Writers never talk to Google. They flip a flag in the database (a `pending` sync record)
 * and ask for a debounced flush, so a request on the website costs one tiny query.
 */
class SheetSyncDispatcher
{
    /** Mark a submission as needing a push. Returns the link id, or null when the form has no active link. */
    public function markPending(int $submissionId, int $formId): ?int
    {
        $link = FormSheetLink::where('form_id', $formId)->where('sync_enabled', true)->first(['id']);
        if (! $link) {
            return null;
        }

        SheetSyncRecord::updateOrCreate(
            ['form_submission_id' => $submissionId, 'form_sheet_link_id' => $link->id],
            ['status' => 'pending', 'attempts' => 0, 'last_error' => null],
        );

        return $link->id;
    }

    /** New or edited submission: mark pending and (optionally) schedule the batched flush. */
    public function changed(FormSubmission $submission, bool $flush = true): void
    {
        $linkId = $this->markPending($submission->id, $submission->form_id);

        if ($linkId && $flush) {
            FlushSheetLink::dispatchDebounced($linkId);
        }
    }

    /**
     * A submission is about to be deleted: remember it so the sheet row can be marked "Deleted".
     * (The sync record disappears with the submission, the tombstone does not.)
     */
    public function tombstone(FormSubmission $submission): void
    {
        $link = FormSheetLink::where('form_id', $submission->form_id)->where('sync_enabled', true)->first(['id']);
        if (! $link) {
            return;
        }

        SheetPendingDelete::updateOrCreate(
            ['form_sheet_link_id' => $link->id, 'submission_id' => $submission->id],
            ['entry_number' => $submission->entry_number],
        );

        FlushSheetLink::dispatchDebounced($link->id);
    }
}
