<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = ['event_id', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}
