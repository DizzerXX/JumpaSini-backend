<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReportHalalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reported_as_halal' => ['required', 'boolean'],
            'session_id'        => ['nullable', 'string'],
        ];
    }
}
