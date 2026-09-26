<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::withCount('forms')->latest()->get();
        return Inertia::render('Admin/Events/Index', ['events' => $events]);
    }

    public function create()
    {
        return Inertia::render('Admin/Events/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location'    => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'in:draft,published,closed'],
        ]);

        $data['admin_id'] = Auth::guard('admin')->id();
        $data['slug']     = Str::slug($data['name']);

        $event = Event::create($data);

        return redirect()->route('admin.events.index');
    }

    public function show(Event $event)
    {
        $event->load('forms');
        return Inertia::render('Admin/Events/Forms', [
            'event' => $event,
            'forms' => $event->forms->map(fn($f) => [
                ...$f->toArray(),
                'field_count'      => $f->fields()->count(),
                'submission_count' => $f->submissions()->count(),
            ]),
        ]);
    }

    public function edit(Event $event) { /* TODO */ }

    /**
     * Toggle event status between draft ↔ published.
     * A closed event is not toggled.
     */
    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,published,closed'],
        ]);

        $event->update(['status' => $data['status']]);

        return redirect()->back()->with('success', 'Event status updated.');
    }

    /**
     * Permanently delete the event and all related forms.
     */
    public function destroy(Event $event)
    {
        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Event deleted.');
    }
}
