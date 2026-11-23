<?php

namespace App\Http\Requests;

use App\Support\Iso8601;
use Illuminate\Foundation\Http\FormRequest;

class UpdateElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'start_at' => ['sometimes', 'string', 'regex:' . Iso8601::REGEX],
            'end_at' => ['sometimes', 'string', 'regex:' . Iso8601::REGEX],
        ];
    }
}
