<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormFieldController extends Controller
{
    public function store(Request $request, Form $form)
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:255'],
            'field_type' => ['required', 'string'],
            'options'    => ['nullable', 'array'],
            'help_text'  => ['nullable', 'string', 'max:255'],
            'is_required'=> ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $data['form_id']   = $form->id;
        $data['field_key'] = $request->input('field_key') ?: Str::snake($data['label']);

        $field = FormField::create($data);

        return back()->with('success', 'Field added.');
    }

    public function update(Request $request, FormField $field)
    {
        $data = $request->validate([
            'label'      => ['sometimes', 'string', 'max:255'],
            'field_key'  => ['sometimes', 'string'],
            'options'    => ['nullable', 'array'],
            'help_text'  => ['nullable', 'string', 'max:255'],
            'is_required'=> ['boolean'],
        ]);

        $field->update($data);

        return back()->with('success', 'Field updated.');
    }

    public function destroy(FormField $field)
    {
        $field->delete();
        return back()->with('success', 'Field deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => ['required', 'array']]);

        foreach ($request->order as $item) {
            FormField::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Order saved.');
    }
}
