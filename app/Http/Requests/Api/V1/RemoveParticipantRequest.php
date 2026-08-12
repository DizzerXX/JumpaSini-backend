<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RemoveParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Proves the caller is the participant being removed — issued once,
            // in the addParticipant response, and never surfaced again.
            'access_token' => ['required', 'string'],
        ];
    }
}
