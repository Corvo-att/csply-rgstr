<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a submission looked like BEFORE an edit replaced it. Append-only. */
class SubmissionRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['form_submission_id', 'version', 'snapshot', 'source', 'actor_type', 'actor_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }
}
