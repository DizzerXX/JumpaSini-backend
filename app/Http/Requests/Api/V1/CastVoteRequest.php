<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CastVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'google_place_id' => ['required', 'string', 'max:255'],
            'venue_name'      => ['required', 'string', 'max:255'],
            // Votes are keyed on participant_id, so identity is required and proven
            // with the token issued at join time. participant_name is display-only.
            'participant_id'   => ['required', 'uuid'],
            'access_token'     => ['required', 'string'],
            'participant_name' => ['nullable', 'string', 'max:100'],
            'vote'             => ['required', 'string', 'in:up,down'],
        ];
    }
}
