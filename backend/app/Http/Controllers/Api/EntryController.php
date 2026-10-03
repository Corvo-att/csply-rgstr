<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Read-only feed of cosplay entries for the EGYCON Voting system.
 *
 *   GET /api/v1/forms/{form}/entries?status=approved&updated_since=2026-08-01T00:00:00Z
 *   GET /api/v1/forms/{form}/entries/{entry_number}
 *
 * Auth: Authorization: Bearer <VOTING_API_TOKEN>   (see config/services.php)
 *
 * entry_number is the stable id judges and the voting system should use: it is unique
 * per form, never reused, and is what the cosplayer is told on their profile.
 */
class EntryController extends Controller
{
    public function index(Request $request, Form $form): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,all'],
            'updated_since' => ['nullable', 'date'],
        ]);

        // Judges should only ever see entries a manager approved, unless they ask otherwise.
        $status = $request->query('status', 'approved');

        $query = $form->submissions()->with(['cosplayer.user', 'values'])->orderBy('entry_number');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($since = $request->query('updated_since')) {
            $query->where('updated_at', '>=', $since);
        }

        $fields = $form->fields()->get()->filter->holdsAnswer()->keyBy('id');

        return response()->json([
            'form' => $form->only('id', 'name', 'event_id'),
            'event' => $form->event->only('id', 'name'),
            'entries' => $query->get()->map(fn ($s) => $this->entry($s, $fields))->values(),
        ]);
    }

    public function show(Form $form, int $entryNumber): JsonResponse
    {
        $submission = $form->submissions()->with(['cosplayer.user', 'values'])->where('entry_number', $entryNumber)->firstOrFail();
        $fields = $form->fields()->get()->filter->holdsAnswer()->keyBy('id');

        return response()->json(['entry' => $this->entry($submission, $fields)]);
    }

    /** @param Collection<int, FormField> $fields */
    private function entry(FormSubmission $submission, Collection $fields): array
    {
        $answers = [];
        $media = ['images' => [], 'videos' => [], 'files' => []];

        foreach ($submission->values as $value) {
            $field = $fields->get($value->form_field_id);
            if (! $field) {
                continue;
            }

            $decoded = $value->value;
            if (in_array($field->field_type, [...FormField::MULTI_TYPES, 'address'], true)) {
                $decoded = json_decode((string) $value->value, true) ?? $value->value;
            }

            $answers[] = [
                'key' => $field->field_key,
                'label' => $field->label,
                'type' => $field->field_type,
                'value' => $decoded,
            ];

            if ($field->field_type === 'image') {
                $media['images'][] = ['field' => $field->field_key, 'url' => $value->value];
            } elseif ($field->field_type === 'video') {
                $media['videos'][] = [
                    'field' => $field->field_key,
                    'url' => $value->value,
                    // "ready" means normalised for the show; anything else is the raw upload
                    'processing' => $value->processing_status ?? 'ready',
                    'duration' => $value->meta['duration'] ?? null,
                    'width' => $value->meta['width'] ?? null,
                    'height' => $value->meta['height'] ?? null,
                ];
            } elseif ($field->field_type === 'file') {
                $media['files'][] = ['field' => $field->field_key, 'url' => $value->value];
            }
        }

        $cosplayer = $submission->cosplayer;

        return [
            'entry_number' => $submission->entry_number,
            'submission_id' => $submission->id,
            'status' => $submission->status,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'updated_at' => $submission->updated_at?->toIso8601String(),
            'cosplayer' => [
                'id' => $cosplayer->id,
                'name' => $cosplayer->user->name,
                'character_name' => $cosplayer->character_name,
                'series' => $cosplayer->series,
                'experience_level' => $cosplayer->experience_level,
                'bio' => $cosplayer->bio,
            ],
            'answers' => $answers,
            'media' => $media,
        ];
    }
}
