<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Inertia\Inertia;

class EventController extends Controller
{
    /** Public welcome page - shows published events. */
    public function welcome()
    {
        return Inertia::render('Welcome', [
            'events' => Event::where('status', 'published')
                ->latest()
                ->get(['id', 'name', 'description', 'location', 'starts_at', 'ends_at', 'status']),
        ]);
    }
}
