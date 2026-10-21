<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'admin_id',
        'form_id',
        'event_id',
        'source_type',
        'spreadsheet_id',
        'google_connection_id',
        'original_filename',
        'stored_path',
        'file_hash',
        'sheet_name',
        'header_row',
        'column_map',
        'options',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'imported_rows',
        'skipped_rows',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map'    => 'array',
            'options'       => 'array',
            'header_row'    => 'integer',
            'total_rows'    => 'integer',
            'valid_rows'    => 'integer',
            'invalid_rows'  => 'integer',
            'imported_rows' => 'integer',
            'skipped_rows'  => 'integer',
            'started_at'    => 'datetime',
            'finished_at'   => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function rows()
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    public function validRows()
    {
        return $this->hasMany(ImportRow::class)->where('status', 'valid');
    }

    public function invalidRows()
    {
        return $this->hasMany(ImportRow::class)->where('status', 'invalid');
    }
}
