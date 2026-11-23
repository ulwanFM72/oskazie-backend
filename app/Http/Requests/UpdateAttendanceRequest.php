<?php

namespace App\Http\Requests;

use App\Services\TurnoutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['hadir', 'tidak_hadir', 'belum_dicatat'])],
            'absence_reason' => ['required_if:status,tidak_hadir', 'nullable', Rule::in(TurnoutService::REASONS)],
            'absence_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'absence_reason.required_if' => 'Alasan ketidakhadiran wajib dipilih.',
        ];
    }
}
