<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoterLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['siswa', 'guru'])],
            // siswa: NIS; guru: nama lengkap
            'identifier' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'NIS atau nama wajib diisi.',
        ];
    }
}
