<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JudgingSheet extends Model
{
    protected $fillable = [
        'admin_id',
        'form_id',
        'judging_rubric_id',
        'title',
        'google_connection_id',
        'spreadsheet_id',
        'spreadsheet_url',
        'judge_names',
        'options',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'judge_names' => 'array',
            'options'     => 'array',
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

    public function rubric()
    {
        return $this->belongsTo(JudgingRubric::class, 'judging_rubric_id');
    }

    public function connection()
    {
        return $this->belongsTo(GoogleConnection::class, 'google_connection_id');
    }
}
