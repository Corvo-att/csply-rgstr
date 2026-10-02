<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Exports\DynamicFormSubmissionsExport;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SubmissionController extends Controller
{
    public function index(Form $form)
    {
        $fields = $form->fields()->get();

        $submissions = $form->submissions()
            ->with(['cosplayer.user', 'values'])
            ->latest()
            ->get()
            ->map(function ($sub) use ($fields) {
                return [
                    'id'           => $sub->id,
                    'cosplayer'    => [
                        'name'           => $sub->cosplayer->user->name,
                        'character_name' => $sub->cosplayer->character_name,
                        'series'         => $sub->cosplayer->series,
                    ],
                    'submitted_at' => $sub->submitted_at?->toDateTimeString(),
                    'values'       => $sub->values->keyBy('form_field_id')->map->value,
                ];
            });

        return Inertia::render('Admin/Forms/Submissions', [
            'form'        => $form,
            'fields'      => $fields,
            'submissions' => $submissions,
        ]);
    }

    /**
     * Show a single submission in detail (admin view).
     */
    public function show(Form $form, FormSubmission $submission)
    {
        abort_if($submission->form_id !== $form->id, 404);

        $submission->load(['cosplayer.user', 'values.field']);

        $fields = $form->fields()->orderBy('sort_order')->get();

        // Build a keyed map of field_id → value
        $valuesMap = $submission->values->keyBy('form_field_id')->map->value;

        return Inertia::render('Admin/Forms/SubmissionDetail', [
            'form'       => $form->only('id', 'name', 'event_id'),
            'fields'     => $fields,
            'submission' => [
                'id'           => $submission->id,
                'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'cosplayer'    => [
                    'name'             => $submission->cosplayer->user->name,
                    'email'            => $submission->cosplayer->user->email,
                    'character_name'   => $submission->cosplayer->character_name,
                    'series'           => $submission->cosplayer->series,
                    'experience_level' => $submission->cosplayer->experience_level,
                    'bio'              => $submission->cosplayer->bio,
                ],
                'values' => $valuesMap,
            ],
        ]);
    }

    public function export(Form $form)
    {
        return Excel::download(
            new DynamicFormSubmissionsExport($form),
            Str::slug($form->name) . '_submissions.xlsx'
        );
    }
}
