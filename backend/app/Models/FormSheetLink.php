<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSheetLink extends Model
{
    protected $fillable = [
        'form_id',
        'google_connection_id',
        'spreadsheet_id',
        'spreadsheet_url',
        'spreadsheet_title',
        'sheet_gid',
        'sheet_title',
        'header_row',
        'sync_enabled',
        'auto_add_new_fields',
        'include_system_columns',
        'status',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'sheet_gid'              => 'integer',
            'header_row'             => 'integer',
            'sync_enabled'           => 'boolean',
            'auto_add_new_fields'    => 'boolean',
            'include_system_columns' => 'array',
            'last_synced_at'         => 'datetime',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function connection()
    {
        return $this->belongsTo(GoogleConnection::class, 'google_connection_id');
    }

    public function mappings()
    {
        return $this->hasMany(SheetColumnMapping::class)->orderBy('column_index');
    }

    public function activeMappings()
    {
        return $this->hasMany(SheetColumnMapping::class)
            ->where('is_active', true)
            ->orderBy('column_index');
    }

    public function syncRecords()
    {
        return $this->hasMany(SheetSyncRecord::class);
    }
}
