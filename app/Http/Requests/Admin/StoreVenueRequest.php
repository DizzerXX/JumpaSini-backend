<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:255'],
            'address'           => ['required', 'string', 'max:500'],
            'latitude'          => ['required', 'numeric', 'between:2.5,6.5'],
            'longitude'         => ['required', 'numeric', 'between:99.5,104.5'],
            'type'              => ['required', Rule::in(['mall', 'restaurant', 'cafe', 'park', 'beach', 'waterfall', 'river'])],
            'is_halal'          => ['nullable', 'boolean'],
            'is_halal_verified' => ['boolean'],
            'google_place_id'   => ['nullable', 'string', 'unique:venues,google_place_id'],
            'google_rating'     => ['nullable', 'numeric', 'between:0,5'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'website'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
