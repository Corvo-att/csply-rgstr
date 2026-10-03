<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormSettingsRequest;
use App\Models\Event;
use App\Models\Form;

class AdminFormController extends Controller
{
    /** Create a new form under an event, then open the builder. */
    public function store(FormSettingsRequest $request, Event $event)
    {
        $form = $event->forms()->create($request->validated());

        return redirect()->route('admin.forms.builder', [$event, $form]);
    }

    /** Change a form's settings: name, open/closed, schedule, entry limit. */
    public function update(FormSettingsRequest $request, Event $event, Form $form)
    {
        abort_if($form->event_id !== $event->id, 404);

        $form->update($request->validated());

        return back()->with('success', 'Form updated.');
    }
}
