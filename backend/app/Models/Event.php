<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'admin_id', 'name', 'slug', 'description',
        'starts_at', 'ends_at', 'location', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function forms()
    {
        return $this->hasMany(Form::class);
    }
}
