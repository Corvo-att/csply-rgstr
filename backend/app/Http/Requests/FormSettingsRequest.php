<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / update a form's settings (not its fields). */
class FormSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $p = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$p, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', Rule::when($this->filled('opens_at'), 'after:opens_at')],
            'max_submissions' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
