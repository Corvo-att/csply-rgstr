<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $fillable = ['event_id', 'name', 'description', 'is_active', 'opens_at', 'closes_at', 'max_submissions'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
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

    /**
     * Why this form cannot accept a response right now, or null when it can.
     * One place decides this so the fill page, the submit action and the
     * profile page can never disagree.
     */
    public function closedReason(): ?string
    {
        if ($this->event?->status !== 'published') {
            return 'This event is not open for registration.';
        }
        if (! $this->is_active) {
            return 'This form is not currently accepting responses.';
        }
        if ($this->opens_at && now()->lt($this->opens_at)) {
            return 'Registration opens on '.$this->opens_at->format('M j, Y H:i').'.';
        }
        if ($this->closes_at && now()->gte($this->closes_at)) {
            return 'Registration for this form has closed.';
        }
        if ($this->max_submissions && $this->submissions()->count() >= $this->max_submissions) {
            return 'This form has reached its maximum number of entries.';
        }

        return null;
    }
}
