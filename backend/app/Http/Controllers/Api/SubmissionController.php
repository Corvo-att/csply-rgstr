<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\User;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function submit(Request $request, $formId)
    {
        $form = Form::findOrFail($formId);
        $userId = $request->input('user_id');
        $values = $request->input('values', []);

        // Find or create cosplayer
        $cosplayer = null;
        if ($userId) {
            $cosplayer = Cosplayer::where('user_id', $userId)->first();
            if (! $cosplayer) {
                $user = User::find($userId);
                $cosplayer = Cosplayer::create([
                    'user_id'          => $userId,
                    'character_name'   => $user->name ?? 'Attendee',
                    'series'           => 'General',
                    'experience_level' => 'beginner',
                ]);
            }
        } else {
            $firstCosplayer = Cosplayer::first();
            $cosplayer = $firstCosplayer;
        }

        if (! $cosplayer) {
            return response()->json(['ok' => false, 'message' => 'Cosplayer profile not found.'], 400);
        }

        $submission = FormSubmission::create([
            'form_id'      => $form->id,
            'cosplayer_id' => $cosplayer->id,
            'submitted_at' => now(),
        ]);

        $fields = $form->fields()->get()->keyBy('field_key');

        foreach ($values as $key => $val) {
            $field = $fields->get($key);
            if ($field) {
                $valString = is_array($val) ? json_encode($val) : (string) $val;
                FormSubmissionValue::create([
                    'form_submission_id' => $submission->id,
                    'form_field_id'      => $field->id,
                    'value'              => $valString,
                ]);
            }
        }

        return response()->json([
            'ok'            => true,
            'submission_id' => $submission->id,
        ], 201);
    }

    public function getSubmissions($formId)
    {
        $form = Form::with('event')->findOrFail($formId);
        $fields = $form->fields()->orderBy('sort_order')->get();

        $submissions = $form->submissions()
            ->with(['cosplayer.user', 'values'])
            ->latest()
            ->get()
            ->map(function ($sub) {
                $valueMap = [];
                foreach ($sub->values as $v) {
                    $valueMap[$v->form_field_id] = $v->value;
                }

                return [
                    'id'           => $sub->id,
                    'cosplayer'    => [
                        'name'           => $sub->cosplayer?->user?->name ?? 'Unknown',
                        'character_name' => $sub->cosplayer?->character_name ?? 'N/A',
                        'series'         => $sub->cosplayer?->series ?? 'N/A',
                    ],
                    'submitted_at' => $sub->submitted_at?->toISOString() ?? $sub->created_at?->toISOString(),
                    'values'       => $valueMap,
                ];
            });

        return response()->json([
            'form'        => $form,
            'event'       => $form->event,
            'fields'      => $fields,
            'submissions' => $submissions,
        ]);
    }

    public function dashboard()
    {
        $totalCosplayers  = Cosplayer::count();
        $totalEvents      = Event::count();
        $publishedEvents  = Event::where('status', 'published')->count();
        $totalSubmissions = FormSubmission::count();

        $cosplayers = Cosplayer::with('user')
            ->latest()
            ->get()
            ->map(function ($c) {
                return [
                    'id'               => $c->id,
                    'user_id'          => $c->user_id,
                    'name'             => $c->user?->name ?? 'Attendee',
                    'email'            => $c->user?->email ?? '',
                    'character_name'   => $c->character_name,
                    'series'           => $c->series,
                    'experience_level' => $c->experience_level,
                    'bio'              => $c->bio,
                    'created_at'       => $c->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'stats' => [
                'total_cosplayers'  => $totalCosplayers,
                'total_events'      => $totalEvents,
                'published_events'  => $publishedEvents,
                'total_submissions' => $totalSubmissions,
            ],
            'cosplayers' => $cosplayers,
        ]);
    }
}
