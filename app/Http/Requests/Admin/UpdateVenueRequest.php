<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $venueId = $this->route('id');

        return [
            'name'              => ['sometimes', 'string', 'max:255'],
            'address'           => ['sometimes', 'string', 'max:500'],
            'latitude'          => ['sometimes', 'numeric', 'between:2.5,6.5'],
            'longitude'         => ['sometimes', 'numeric', 'between:99.5,104.5'],
            'type'              => ['sometimes', Rule::in(['mall', 'restaurant', 'cafe', 'park', 'beach', 'waterfall', 'river'])],
            'is_halal'          => ['nullable', 'boolean'],
            'is_halal_verified' => ['sometimes', 'boolean'],
            'google_place_id'   => ['nullable', 'string', Rule::unique('venues', 'google_place_id')->ignore($venueId)],
            'google_rating'     => ['nullable', 'numeric', 'between:0,5'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'website'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
