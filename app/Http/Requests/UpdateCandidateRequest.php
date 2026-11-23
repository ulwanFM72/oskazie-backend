<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'number_order' => ['sometimes', 'integer', 'between:1,99'],
            'chairman_name' => ['sometimes', 'string', 'max:150'],
            'chairman_class' => ['sometimes', 'string', 'max:30'],
            'vice_name' => ['sometimes', 'string', 'max:150'],
            'vice_class' => ['sometimes', 'string', 'max:30'],
            'vision' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'mission' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'work_program' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'profile' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }
}
