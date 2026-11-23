<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoterRequest extends FormRequest
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
            'type' => ['required', Rule::in(['siswa', 'guru'])],
            'name' => ['required', 'string', 'max:150'],
            'nis' => ['required_if:type,siswa', 'nullable', 'string', 'regex:/^\d{5,15}$/'],
            'class_name' => ['required_if:type,siswa', 'nullable', 'string', 'max:30'],
            'unit' => ['required_if:type,guru', 'nullable', 'string', 'max:60'],
            'login_key' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nis.regex' => 'NIS harus berupa angka 5 sampai 15 digit.',
            'nis.required_if' => 'NIS wajib diisi untuk siswa.',
            'class_name.required_if' => 'Kelas wajib diisi untuk siswa.',
            'unit.required_if' => 'Unit wajib diisi untuk guru/staf.',
        ];
    }
}
