<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nis')) {
            $this->merge(['nis' => preg_replace('/\s+/', '', (string) $this->input('nis'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'nis' => ['sometimes', 'string', 'regex:/^\d{5,15}$/'],
            'class_name' => ['sometimes', 'string', 'max:30'],
            'unit' => ['sometimes', 'string', 'max:60'],
            'login_key' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['nis.regex' => 'NIS harus berupa angka 5 sampai 15 digit.'];
    }
}
