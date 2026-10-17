<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create / update an event. On update every key is optional ("sometimes") so the
 * publish/unpublish button can send just { status }.
 */
class EventRequest extends FormRequest
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
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => [$p, 'in:draft,published,closed'],
        ];
    }
}
