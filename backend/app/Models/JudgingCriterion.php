<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JudgingCriterion extends Model
{
    protected $table = 'judging_criteria';

    protected $fillable = [
        'judging_rubric_id',
        'name',
        'max_points',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function rubric()
    {
        return $this->belongsTo(JudgingRubric::class, 'judging_rubric_id');
    }
}
