<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FormSubmission extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'form_id',
        'cosplayer_id',
        'entry_number',
        'status',
        'admin_notes',
        'source',
        'import_batch_id',
        'submitted_at',
        'last_edited_at',
        'version',
    ];

    protected static function booted(): void
    {
        // Every submission gets a permanent public identifier (safe to share with other systems later).
        static::creating(function (self $submission) {
            $submission->uuid ??= (string) Str::uuid();
            $submission->version ??= 1;
        });
    }

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function cosplayer()
    {
        return $this->belongsTo(Cosplayer::class);
    }

    public function values()
    {
        return $this->hasMany(FormSubmissionValue::class);
    }

    public function sheetSyncRecords()
    {
        return $this->hasMany(SheetSyncRecord::class);
    }

    public function revisions()
    {
        return $this->hasMany(SubmissionRevision::class)->orderByDesc('version');
    }
}
