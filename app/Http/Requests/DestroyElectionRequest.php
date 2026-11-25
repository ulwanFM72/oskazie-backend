<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DestroyElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Pengaman: admin harus mengetik HAPUS agar tidak terhapus karena salah klik.
            'confirm' => ['required', 'string', 'in:HAPUS'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'Ketik HAPUS untuk mengonfirmasi.',
            'confirm.in' => 'Ketik HAPUS (huruf kapital) untuk mengonfirmasi.',
        ];
    }
}
