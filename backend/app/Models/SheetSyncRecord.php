<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SheetSyncRecord extends Model
{
    protected $fillable = [
        'form_submission_id',
        'form_sheet_link_id',
        'sheet_row',
        'status',
        'attempts',
        'last_error',
        'synced_at',
        'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'sheet_row' => 'integer',
            'attempts'  => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function link()
    {
        return $this->belongsTo(FormSheetLink::class, 'form_sheet_link_id');
    }
}
