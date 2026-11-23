<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportVoterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['siswa', 'guru'])],
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['file.extensions' => 'File harus berformat CSV. Di Excel: Simpan Sebagai, CSV (UTF-8).'];
    }
}
