<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use Inertia\Inertia;

class FormBuilderController extends Controller
{
    /** Show the form builder page with all fields */
    public function edit(Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);

        return Inertia::render('Admin/Forms/Builder', [
            'event'  => $event,
            'form'   => $form,
            'fields' => $form->fields()->get(),
        ]);
    }

    /** Delete a form (and its fields) from an event */
    public function destroy(Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);

        $form->delete();

        return redirect()->route('admin.events.show', $event)->with('success', 'Form deleted.');
    }
}
