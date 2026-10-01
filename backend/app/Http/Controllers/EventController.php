<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * Public welcome page — shows published events.
     */
    public function welcome()
    {
        $events = Event::where('status', 'published')
            ->latest()
            ->get(['id', 'name', 'description', 'location', 'starts_at', 'ends_at', 'status']);

        return Inertia::render('Welcome', [
            'events' => $events,
            'auth'   => [
                'user' => Auth::check() ? Auth::user()->only('id', 'name', 'email') : null,
            ],
        ]);
    }
}
