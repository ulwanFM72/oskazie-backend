<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$presence, 'string', 'max:200'],
            'body' => [$presence, 'string', 'max:10000'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
