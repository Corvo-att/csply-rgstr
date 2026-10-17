<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CosplayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'character_name' => ['required', 'string', 'max:255'],
            'series' => ['required', 'string', 'max:255'],
            'experience_level' => ['required', 'in:beginner,intermediate,advanced,professional'],
            'bio' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
