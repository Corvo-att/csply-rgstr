<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveFormFieldsRequest;
use App\Models\Form;
use App\Models\FormField;
use App\Services\SubmissionFiles;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FormFieldController extends Controller
{
    /**
     * Save the builder's complete, ordered field list.
     *
     * Fields that already exist (they arrive with their id) are UPDATED in place, new ones
     * are created, and only fields missing from the list are deleted. This matters: answers
     * that cosplayers already submitted point at field ids, so recreating every field on each
     * save (as the first version did) wiped all submitted data.
     */
    public function saveBatch(SaveFormFieldsRequest $request, Form $form, SubmissionFiles $files)
    {
        $existing = $form->fields()->get()->keyBy('id');
        $payload = $request->validated('fields');

        // Changing the TYPE of a field that already has answers would corrupt them.
        foreach ($payload as $item) {
            $current = $existing->get($item['id'] ?? 0);
            if ($current && $current->field_type !== $item['field_type'] && $current->hasAnswers()) {
                return back()->withErrors(['fields' => "\"{$current->label}\" already has answers, so its type cannot be changed. Delete it and add a new field instead."]);
            }
        }

        DB::transaction(function () use ($form, $existing, $payload, $files) {
            $keptIds = [];
            $usedKeys = [];

            foreach ($payload as $index => $item) {
                $attributes = [
                    'label' => $item['label'],
                    'field_key' => $this->uniqueKey($item['field_key'] ?? null, $item['label'], $usedKeys),
                    'field_type' => $item['field_type'],
                    'options' => Arr::only($item['options'] ?? [], SaveFormFieldsRequest::OPTION_KEYS),
                    'is_required' => (bool) ($item['is_required'] ?? false),
                    'sort_order' => $index + 1,
                    'help_text' => $item['help_text'] ?? null,
                ];

                $field = $existing->get($item['id'] ?? 0);
                if ($field) {
                    $field->update($attributes);
                } else {
                    $field = $form->fields()->create($attributes);
                }

                $keptIds[] = $field->id;
            }

            $removed = $existing->keys()->diff($keptIds)->values()->all();
            if ($removed) {
                $files->purgeForFields($removed);
                FormField::whereIn('id', $removed)->delete();
            }
        });

        return back()->with('success', 'Form saved.');
    }

    /** snake_case key from the label, guaranteed unique within the form. */
    private function uniqueKey(?string $key, string $label, array &$used): string
    {
        $base = $key ?: (Str::snake(Str::ascii($label)) ?: 'field');
        $candidate = $base;
        $i = 2;

        while (isset($used[$candidate])) {
            $candidate = $base.'_'.$i++;
        }

        $used[$candidate] = true;

        return $candidate;
    }
}
