<?php

namespace App\Http\Controllers;

use App\Models\Cosplayer;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CosplayerController extends Controller
{
    // ── Show cosplay registration form ────────────────────────────────────────
    public function create()
    {
        $cosplayer = Cosplayer::where('user_id', Auth::id())->first();

        return Inertia::render('Cosplay/Register', [
            'cosplayer' => $cosplayer,
        ]);
    }

    // ── Save / update cosplay profile ─────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'character_name'   => ['required', 'string', 'max:255'],
            'series'           => ['required', 'string', 'max:255'],
            'experience_level' => ['required', 'string', 'in:beginner,intermediate,advanced,professional'],
            'bio'              => ['nullable', 'string'],
        ]);

        Cosplayer::updateOrCreate(
            ['user_id' => Auth::id()],
            $validated
        );

        return redirect()->route('cosplay.profile');
    }

    // ── Show cosplayer profile ────────────────────────────────────────────────
    public function profile()
    {
        $user      = Auth::user();
        $cosplayer = Cosplayer::where('user_id', $user->id)->first();

        // Published events with their active forms
        $events = Event::where('status', 'published')
            ->with(['forms' => fn($q) => $q->where('is_active', true)])
            ->latest()
            ->get()
            ->map(fn($ev) => [
                'id'          => $ev->id,
                'name'        => $ev->name,
                'description' => $ev->description,
                'location'    => $ev->location,
                'starts_at'   => $ev->starts_at,
                'ends_at'     => $ev->ends_at,
                'forms'       => $ev->forms->map(fn($f) => [
                    'id'          => $f->id,
                    'name'        => $f->name,
                    'description' => $f->description,
                    'is_active'   => $f->is_active,
                    'event_id'    => $f->event_id,
                ]),
            ]);

        return Inertia::render('Cosplay/Profile', [
            'user'      => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'created_at' => $user->created_at->toDateString(),
            ],
            'cosplayer' => $cosplayer,
            'events'    => $events,
        ]);
    }
}
