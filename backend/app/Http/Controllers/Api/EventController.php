<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $all = $request->boolean('all');

        $query = Event::with(['forms' => function ($q) {
            $q->withCount('fields');
        }])->latest();

        if (! $all) {
            $query->where('status', 'published');
        }

        $events = $query->get()->map(function ($event) {
            return [
                'id'          => $event->id,
                'admin_id'    => $event->admin_id,
                'name'        => $event->name,
                'slug'        => $event->slug,
                'description' => $event->description,
                'starts_at'   => $event->starts_at?->toISOString() ?? $event->starts_at,
                'ends_at'     => $event->ends_at?->toISOString() ?? $event->ends_at,
                'location'    => $event->location,
                'status'      => $event->status,
                'forms_count' => $event->forms->count(),
                'forms'       => $event->forms->map(function ($form) {
                    return [
                        'id'          => $form->id,
                        'event_id'    => $form->event_id,
                        'name'        => $form->name,
                        'description' => $form->description,
                        'is_active'   => (bool) $form->is_active,
                        'fields_count'=> $form->fields_count,
                    ];
                }),
                'created_at'  => $event->created_at?->toISOString(),
            ];
        });

        return response()->json($events);
    }

    public function show($id)
    {
        $event = Event::with(['forms' => function ($q) {
            $q->withCount(['fields', 'submissions']);
        }])->findOrFail($id);

        return response()->json([
            'id'          => $event->id,
            'name'        => $event->name,
            'slug'        => $event->slug,
            'description' => $event->description,
            'starts_at'   => $event->starts_at?->toISOString() ?? $event->starts_at,
            'ends_at'     => $event->ends_at?->toISOString() ?? $event->ends_at,
            'location'    => $event->location,
            'status'      => $event->status,
            'forms'       => $event->forms->map(function ($f) {
                return [
                    'id'               => $f->id,
                    'event_id'         => $f->event_id,
                    'name'             => $f->name,
                    'description'      => $f->description,
                    'is_active'        => (bool) $f->is_active,
                    'field_count'      => $f->fields_count,
                    'submission_count' => $f->submissions_count,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at'   => ['nullable'],
            'ends_at'     => ['nullable'],
            'location'    => ['nullable', 'string', 'max:255'],
            'status'      => ['required', 'in:draft,published,closed'],
        ]);

        $adminId = Admin::first()->id ?? 1;
        $slugBase = Str::slug($validated['name']) ?: 'event';
        $slug = $slugBase;
        $counter = 1;
        while (Event::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$counter}";
            $counter++;
        }

        $event = Event::create([
            'admin_id'    => $adminId,
            'name'        => $validated['name'],
            'slug'        => $slug,
            'description' => $validated['description'] ?? null,
            'starts_at'   => ! empty($validated['starts_at']) ? date('Y-m-d H:i:s', strtotime($validated['starts_at'])) : null,
            'ends_at'     => ! empty($validated['ends_at']) ? date('Y-m-d H:i:s', strtotime($validated['ends_at'])) : null,
            'location'    => $validated['location'] ?? null,
            'status'      => $validated['status'],
        ]);

        return response()->json([
            'ok'    => true,
            'event' => $event,
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,published,closed'],
        ]);

        $event = Event::findOrFail($id);
        $event->update(['status' => $validated['status']]);

        return response()->json([
            'ok'    => true,
            'event' => $event,
        ]);
    }

    public function destroy($id)
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json(['ok' => true]);
    }
}
