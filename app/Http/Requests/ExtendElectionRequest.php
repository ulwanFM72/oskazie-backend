<?php

namespace App\Http\Requests;

use App\Support\Iso8601;
use Illuminate\Foundation\Http\FormRequest;

class ExtendElectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'end_at' => ['required', 'string', 'regex:' . Iso8601::REGEX],
        ];
    }
}
