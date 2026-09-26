<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormController extends Controller
{
    public function indexByEvent($eventId)
    {
        $event = Event::findOrFail($eventId);
        $forms = $event->forms()->withCount(['fields', 'submissions'])->latest()->get()->map(function ($f) {
            return [
                'id'               => $f->id,
                'event_id'         => $f->event_id,
                'name'             => $f->name,
                'description'      => $f->description,
                'is_active'        => (bool) $f->is_active,
                'field_count'      => $f->fields_count,
                'submission_count' => $f->submissions_count,
            ];
        });

        return response()->json([
            'event' => $event,
            'forms' => $forms,
        ]);
    }

    public function store(Request $request, $eventId)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ]);

        $event = Event::findOrFail($eventId);

        $form = $event->forms()->create([
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'ok'   => true,
            'form' => [
                'id'               => $form->id,
                'event_id'         => $form->event_id,
                'name'             => $form->name,
                'description'      => $form->description,
                'is_active'        => (bool) $form->is_active,
                'field_count'      => 0,
                'submission_count' => 0,
            ],
        ], 201);
    }

    public function toggleStatus($id)
    {
        $form = Form::findOrFail($id);
        $form->update(['is_active' => ! $form->is_active]);

        return response()->json([
            'ok'        => true,
            'is_active' => (bool) $form->is_active,
        ]);
    }

    public function destroy($id)
    {
        $form = Form::findOrFail($id);
        $form->delete();

        return response()->json(['ok' => true]);
    }

    public function show($id)
    {
        $form = Form::with(['event'])->findOrFail($id);
        $fields = $form->fields()->orderBy('sort_order')->get()->map(function ($field) {
            return [
                'id'          => $field->id,
                'form_id'     => $field->form_id,
                'label'       => $field->label,
                'field_key'   => $field->field_key,
                'field_type'  => $field->field_type,
                'options'     => is_array($field->options) ? $field->options : json_decode($field->options ?? '{}', true),
                'is_required' => (bool) $field->is_required,
                'sort_order'  => (int) $field->sort_order,
                'help_text'   => $field->help_text,
            ];
        });

        return response()->json([
            'form'   => $form,
            'event'  => $form->event,
            'fields' => $fields,
        ]);
    }

    public function saveFieldsBatch(Request $request, $id)
    {
        $form = Form::findOrFail($id);
        $fields = $request->input('fields', []);

        // Replace/sync fields
        $form->fields()->delete();

        $savedFields = [];
        foreach ($fields as $idx => $f) {
            $label = $f['label'] ?? 'Field ' . ($idx + 1);
            $key = ! empty($f['field_key']) ? $f['field_key'] : (Str::snake($label) ?: 'field_' . ($idx + 1));
            
            $saved = FormField::create([
                'form_id'    => $form->id,
                'label'      => $label,
                'field_key'  => $key,
                'field_type' => $f['field_type'] ?? 'text',
                'options'    => is_array($f['options'] ?? null) ? $f['options'] : (isset($f['options']) ? json_decode($f['options'], true) : []),
                'is_required'=> (bool) ($f['is_required'] ?? false),
                'sort_order' => (int) ($f['sort_order'] ?? ($idx + 1)),
                'help_text'  => $f['help_text'] ?? null,
            ]);
            $savedFields[] = $saved;
        }

        return response()->json([
            'ok'     => true,
            'fields' => $savedFields,
        ]);
    }
}
