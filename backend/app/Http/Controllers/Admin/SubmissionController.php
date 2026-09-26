<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Exports\DynamicFormSubmissionsExport;
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

    public function export(Form $form)
    {
        return Excel::download(
            new DynamicFormSubmissionsExport($form),
            Str::slug($form->name) . '_submissions.xlsx'
        );
    }
}
