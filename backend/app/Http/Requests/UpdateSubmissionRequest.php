<?php

namespace App\Http\Requests;

use App\Models\FormSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(FormSubmission::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
