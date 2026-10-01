<?php

namespace App\Http\Controllers;

use App\Models\Cosplayer;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FormController extends Controller
{
    /**
     * Show the form fill page.
     */
    public function fill(Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);
        abort_if(! $form->is_active, 403, 'This form is not currently accepting responses.');

        $cosplayer = Cosplayer::where('user_id', Auth::id())->first();

        return Inertia::render('Forms/Fill', [
            'event'     => $event->only('id', 'name'),
            'form'      => $form->only('id', 'name', 'description'),
            'fields'    => $form->fields()->orderBy('sort_order')->get(),
            'cosplayer' => $cosplayer,
        ]);
    }

    /**
     * Handle form submission.
     */
    public function submit(Request $request, Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);
        abort_if(! $form->is_active, 403);

        $cosplayer = Cosplayer::where('user_id', Auth::id())->firstOrFail();

        $request->validate([
            'values' => ['required', 'array'],
        ]);

        $submission = FormSubmission::create([
            'form_id'      => $form->id,
            'cosplayer_id' => $cosplayer->id,
            'submitted_at' => now(),
        ]);

        foreach ($request->values as $fieldId => $value) {
            $storeValue = $value;

            if (is_array($value)) {
                $storeValue = implode(', ', $value);
            } elseif ($value instanceof \Illuminate\Http\UploadedFile) {
                $extension   = $value->getClientOriginalExtension();
                $fileName    = \Illuminate\Support\Str::random(24) . '.' . $extension;
                $destination = public_path('uploads');

                if (! file_exists($destination)) {
                    mkdir($destination, 0777, true);
                }

                $value->move($destination, $fileName);
                $storeValue = url('uploads/' . $fileName);
            }

            FormSubmissionValue::create([
                'form_submission_id' => $submission->id,
                'form_field_id'      => $fieldId,
                'value'              => $storeValue,
            ]);
        }

        return redirect()->route('cosplay.profile')->with('success', 'Form submitted successfully!');
    }
}
