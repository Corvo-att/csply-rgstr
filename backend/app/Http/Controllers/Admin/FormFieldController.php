<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Batch replace all fields for a form — used by the Builder page.
     */
    public function saveBatch(Request $request, Form $form)
    {
        $request->validate([
            'fields'              => ['required', 'array'],
            'fields.*.id'         => ['nullable'],
            'fields.*.label'      => ['required', 'string', 'max:255'],
            'fields.*.field_key'  => ['required', 'string'],
            'fields.*.field_type' => ['required', 'string'],
            'fields.*.is_required'=> ['boolean'],
            'fields.*.sort_order' => ['integer'],
        ]);

        // Upsert instead of delete + recreate: field IDs must stay stable so
        // stored submission values (and future Google Sheet column mappings)
        // are never lost when the builder is saved.
        DB::transaction(function () use ($request, $form) {
            $existing = $form->fields()->get();
            $keptIds  = [];

            foreach ($request->fields as $idx => $f) {
                $key = $f['field_key'] ?: Str::snake($f['label']);

                $field = null;
                if (isset($f['id']) && is_numeric($f['id'])) {
                    $field = $existing->firstWhere('id', (int) $f['id']);
                }
                $field ??= $existing->firstWhere('field_key', $key);

                $attrs = [
                    'label'       => $f['label'],
                    'field_key'   => $key,
                    'field_type'  => $f['field_type'],
                    'options'     => $f['options'] ?? [],
                    'is_required' => (bool) ($f['is_required'] ?? false),
                    'sort_order'  => (int) ($f['sort_order'] ?? $idx + 1),
                    'help_text'   => $f['help_text'] ?? null,
                ];

                if ($field) {
                    $field->update($attrs);
                } else {
                    $field = FormField::create($attrs + ['form_id' => $form->id]);
                }

                $keptIds[] = $field->id;
            }

            // Only fields the admin actually removed get deleted.
            $form->fields()->whereNotIn('id', $keptIds)->delete();
        });

        return back()->with('success', 'Fields saved.');
    }
}
