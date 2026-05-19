<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class HalalOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_halal'          => ['required', 'boolean'],
            'is_halal_verified' => ['required', 'boolean'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
