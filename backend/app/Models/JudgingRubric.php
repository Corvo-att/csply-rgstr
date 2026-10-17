<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JudgingRubric extends Model
{
    protected $fillable = [
        'form_id',
        'name',
        'description',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function criteria()
    {
        return $this->hasMany(JudgingCriterion::class)->orderBy('sort_order');
    }

    public function sheets()
    {
        return $this->hasMany(JudgingSheet::class);
    }
}
