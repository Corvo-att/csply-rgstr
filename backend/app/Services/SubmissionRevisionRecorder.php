<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\SubmissionRevision;

/** Snapshots a submission before an edit replaces it, so history is never lost. */
class SubmissionRevisionRecorder
{
    public function record(FormSubmission $submission, string $source, ?string $actorType = null, ?int $actorId = null): SubmissionRevision
    {
        $submission->load('values');

        return SubmissionRevision::create([
            'form_submission_id' => $submission->id,
            'version' => (int) $submission->getOriginal('version', $submission->version),
            'snapshot' => [
                // getOriginal: the caller may already have fill()ed the new status/notes onto the model
                'status' => $submission->getOriginal('status', $submission->status),
                'admin_notes' => $submission->getOriginal('admin_notes', $submission->admin_notes),
                'values' => $submission->values
                    ->mapWithKeys(fn ($v) => [($v->field_key_snapshot ?: 'field_'.$v->form_field_id) => $v->value])
                    ->all(),
            ],
            'source' => $source,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ]);
    }
}
