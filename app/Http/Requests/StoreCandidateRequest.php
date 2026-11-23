<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'election_id' => ['sometimes', 'integer', 'exists:elections,id'],
            'number_order' => ['required', 'integer', 'between:1,99'],
            'chairman_name' => ['required', 'string', 'max:150'],
            'chairman_class' => ['required', 'string', 'max:30'],
            'vice_name' => ['required', 'string', 'max:150'],
            'vice_class' => ['required', 'string', 'max:30'],
            'vision' => ['nullable', 'string', 'max:5000'],
            'mission' => ['nullable', 'string', 'max:5000'],
            'work_program' => ['nullable', 'string', 'max:5000'],
            'profile' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
