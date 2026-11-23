<?php

namespace App\Http\Requests;

use App\Support\Iso8601;
use Illuminate\Foundation\Http\FormRequest;

class EndElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Alasan wajib agar keputusan mengakhiri lebih awal dapat dipertanggungjawabkan.
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            // Kosong = akhiri sekarang. Diisi = percepat ke waktu tersebut.
            'end_at' => ['nullable', 'string', 'regex:' . Iso8601::REGEX],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'end_at.regex' => 'Format waktu harus ISO 8601 dengan zona, contoh 2026-12-01T12:00:00+07:00.',
        ];
    }
}
