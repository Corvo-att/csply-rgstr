<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\SubmissionFiles;
use App\Support\ServerLimits;
use Inertia\Inertia;

class FormBuilderController extends Controller
{
    /** Show the form builder page with all fields. */
    public function edit(Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);

        return Inertia::render('Admin/Forms/Builder', [
            'event' => $event,
            'form' => $form,
            'fields' => $form->fields()->get(),
            'submission_count' => $form->submissions()->count(),
            'upload_limit_mb' => ServerLimits::maxUploadMb(),
        ]);
    }

    /** Delete a form with its fields, submissions and uploaded files. */
    public function destroy(Event $event, Form $form, SubmissionFiles $files)
    {
        abort_if($form->event_id !== $event->id, 404);

        $files->purgeForForms([$form->id]);
        $form->delete();

        return redirect()->route('admin.events.show', $event)->with('success', 'Form deleted.');
    }
}
