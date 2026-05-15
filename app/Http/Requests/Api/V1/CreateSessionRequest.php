<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transport_mode' => ['required', 'string', 'in:car,public_transport'],
            'venue_type'     => ['required', 'string', 'in:mall,restaurant,cafe,park,beach,waterfall,river'],
            'halal_filter'   => ['sometimes', 'boolean'],
        ];
    }
}
