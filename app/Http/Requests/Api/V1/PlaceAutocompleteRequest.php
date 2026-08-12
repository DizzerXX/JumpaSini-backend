<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PlaceAutocompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input' => ['required', 'string', 'min:3', 'max:200'],
            // Optional Google session token: groups a user's keystrokes into one
            // billable autocomplete session when the client supplies it.
            'session_token' => ['nullable', 'string', 'max:100'],
        ];
    }
}
