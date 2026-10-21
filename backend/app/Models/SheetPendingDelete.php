<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A submission that was deleted and still has to be marked "Deleted" in the linked sheet. */
class SheetPendingDelete extends Model
{
    protected $fillable = ['form_sheet_link_id', 'submission_id', 'entry_number'];

    public function link()
    {
        return $this->belongsTo(FormSheetLink::class, 'form_sheet_link_id');
    }
}
