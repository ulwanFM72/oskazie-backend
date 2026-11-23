<?php

namespace App\Http\Requests;

use App\Support\Iso8601;
use Illuminate\Foundation\Http\FormRequest;

class StoreElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'start_at' => ['required', 'string', 'regex:' . Iso8601::REGEX],
            'end_at' => ['required', 'string', 'regex:' . Iso8601::REGEX, 'after:start_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_at.regex' => 'Format waktu harus ISO 8601 dengan zona, contoh 2026-12-01T07:00:00+07:00.',
            'end_at.regex' => 'Format waktu harus ISO 8601 dengan zona, contoh 2026-12-01T15:00:00+07:00.',
            'end_at.after' => 'Waktu berakhir harus setelah waktu mulai.',
        ];
    }
}
