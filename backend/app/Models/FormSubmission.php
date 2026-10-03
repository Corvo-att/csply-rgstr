<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = ['form_id', 'cosplayer_id', 'entry_number', 'status', 'admin_notes', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
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
}
