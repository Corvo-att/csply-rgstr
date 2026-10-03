<?php

namespace App\Http\Controllers;

use App\Http\Requests\CosplayerRequest;
use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CosplayerController extends Controller
{
    public function create()
    {
        return Inertia::render('Cosplay/Register', [
            'cosplayer' => Cosplayer::where('user_id', Auth::id())->first(),
        ]);
    }

    public function store(CosplayerRequest $request)
    {
        Cosplayer::updateOrCreate(['user_id' => Auth::id()], $request->validated());

        return redirect()->route('cosplay.profile');
    }

    public function profile()
    {
        $user = Auth::user();
        $cosplayer = Cosplayer::where('user_id', $user->id)->first();

        // Published events with their active forms (eager loaded: 2 queries, not 1 per event)
        $events = Event::where('status', 'published')
            ->with(['forms' => fn ($q) => $q->where('is_active', true)])
            ->latest()
            ->get();

        // This cosplayer's existing submissions, one query for all forms
        $submissions = $cosplayer
            ? FormSubmission::where('cosplayer_id', $cosplayer->id)->get()->keyBy('form_id')
            : collect();

        $events = $events->map(function ($ev) use ($submissions) {
            $ev->forms->each->setRelation('event', $ev);

            return [
                'id' => $ev->id,
                'name' => $ev->name,
                'description' => $ev->description,
                'location' => $ev->location,
                'starts_at' => $ev->starts_at,
                'ends_at' => $ev->ends_at,
                'forms' => $ev->forms->map(function ($f) use ($submissions) {
                    $sub = $submissions->get($f->id);

                    return [
                        'id' => $f->id,
                        'name' => $f->name,
                        'description' => $f->description,
                        'is_active' => $f->is_active,
                        'event_id' => $f->event_id,
                        'closes_at' => $f->closes_at,
                        'closed_reason' => $sub ? null : $f->closedReason(),
                        'submission' => $sub ? [
                            'entry_number' => $sub->entry_number,
                            'status' => $sub->status,
                            'submitted_at' => $sub->submitted_at,
                        ] : null,
                    ];
                }),
            ];
        });

        return Inertia::render('Cosplay/Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toDateString(),
            ],
            'cosplayer' => $cosplayer,
            'events' => $events,
        ]);
    }
}
