<?php

namespace App\Http\Requests;

use App\Models\FormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Validates the whole field list sent by the form builder. */
class SaveFormFieldsRequest extends FormRequest
{
    /** Option keys the renderer understands. Anything else is dropped on save. */
    public const OPTION_KEYS = [
        'choices', 'min', 'max', 'step', 'min_length', 'max_length', 'max_stars', 'rows',
        'on_label', 'off_label', 'text', 'terms_text', 'default_value',
        'max_size_mb', 'max_size_kb', 'accepted_formats',
        'max_duration_seconds', 'min_duration_seconds', 'over_length_action', 'max_height', 'normalize',
        'max_dimension',
    ];

    public function authorize(): bool
    {
        return true; // role is enforced by the admin.role route middleware
    }

    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:100'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'fields.*.field_type' => ['required', 'string', Rule::in(FormField::TYPES)],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.is_required' => ['boolean'],
            'fields.*.help_text' => ['nullable', 'string', 'max:255'],
            'fields.*.options.text' => ['nullable', 'string', 'max:5000'],
            'fields.*.options.over_length_action' => ['nullable', Rule::in(['reject', 'trim'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $fields = (array) $this->input('fields', []);
            $keys = [];

            foreach ($fields as $i => $f) {
                $label = $f['label'] ?? 'Field '.($i + 1);
                $type = $f['field_type'] ?? '';
                $opts = $f['options'] ?? [];

                if (in_array($type, FormField::CHOICE_TYPES, true) && count(array_filter((array) ($opts['choices'] ?? []))) < 1) {
                    $validator->errors()->add("fields.$i.options", "\"{$label}\" needs at least one choice.");
                }

                foreach (['max_size_mb', 'max_size_kb', 'max_duration_seconds', 'min_duration_seconds', 'max_height', 'max_dimension'] as $numeric) {
                    if (isset($opts[$numeric]) && $opts[$numeric] !== '' && (! is_numeric($opts[$numeric]) || $opts[$numeric] < 0)) {
                        $validator->errors()->add("fields.$i.options", "\"{$label}\": {$numeric} must be a positive number.");
                    }
                }

                if (! empty($f['field_key'])) {
                    if (isset($keys[$f['field_key']])) {
                        $validator->errors()->add("fields.$i.field_key", "Field key \"{$f['field_key']}\" is used twice.");
                    }
                    $keys[$f['field_key']] = true;
                }
            }
        }];
    }
}
