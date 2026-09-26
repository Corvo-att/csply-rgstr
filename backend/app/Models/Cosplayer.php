<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cosplayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'character_name',
        'series',
        'experience_level',
        'bio',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }
}
