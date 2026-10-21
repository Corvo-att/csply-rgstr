<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One "read the sheet, show me what changed" run. Nothing touches submissions until it is applied. */
class SheetPullBatch extends Model
{
    protected $fillable = ['form_sheet_link_id', 'admin_id', 'status', 'changes', 'stats', 'applied_stats', 'error', 'applied_at'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'stats' => 'array',
            'applied_stats' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function link()
    {
        return $this->belongsTo(FormSheetLink::class, 'form_sheet_link_id');
    }
}
