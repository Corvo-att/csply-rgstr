<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Form;
use App\Services\SubmissionFiles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Events/Index', [
            'events' => Event::withCount('forms')->latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Events/Create');
    }

    public function store(EventRequest $request)
    {
        $data = $request->validated();

        $data['admin_id'] = Auth::guard('admin')->id();
        $data['slug'] = $this->uniqueSlug($data['name']);

        Event::create($data);

        return redirect()->route('admin.events.index')->with('success', 'Event created.');
    }

    public function show(Event $event)
    {
        // withCount = 1 query for all counts (was 2 extra queries per form)
        $forms = $event->forms()->withCount(['fields', 'submissions'])->get();

        return Inertia::render('Admin/Events/Forms', [
            'event' => $event,
            'forms' => $forms->map(fn (Form $f) => [
                ...$f->toArray(),
                'field_count' => $f->fields_count,
                'submission_count' => $f->submissions_count,
                // datetime-local inputs want "YYYY-MM-DDTHH:MM" in the app timezone
                'opens_at_input' => $f->opens_at?->format('Y-m-d\TH:i'),
                'closes_at_input' => $f->closes_at?->format('Y-m-d\TH:i'),
            ]),
        ]);
    }

    public function edit(Event $event)
    {
        return Inertia::render('Admin/Events/Create', [
            'event' => [
                ...$event->only('id', 'name', 'description', 'location', 'status'),
                'starts_at' => $event->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    /** Edit event details, or just flip its status (publish / unpublish / close). */
    public function update(EventRequest $request, Event $event)
    {
        $event->update($request->validated());

        // Full edit (from the edit page) goes back to the list; the status buttons stay where they are.
        return $request->has('name')
            ? redirect()->route('admin.events.index')->with('success', 'Event updated.')
            : redirect()->back()->with('success', 'Event updated.');
    }

    /** Permanently delete the event, its forms, submissions and uploaded files. */
    public function destroy(Event $event, SubmissionFiles $files)
    {
        $files->purgeForForms($event->forms()->pluck('id')->all());
        $event->delete();

        return redirect()->route('admin.events.index')->with('success', 'Event deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'event';
        $slug = $base;
        $i = 2;

        while (Event::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
