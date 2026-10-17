<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'raw',
        'normalized',
        'errors',
        'status',
        'form_submission_id',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw'        => 'array',
            'normalized' => 'array',
            'errors'     => 'array',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }
}
