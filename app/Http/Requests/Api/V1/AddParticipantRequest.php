<?php

namespace App\Http\Requests\Api\V1;

use App\Services\BoundaryValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['nullable', 'string', 'max:100'],
            'address'   => ['required', 'string', 'max:255'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['latitude', 'longitude'])) {
                    return;
                }

                $withinBounds = app(BoundaryValidationService::class)->isWithinKLSelangor(
                    (float) $this->input('latitude'),
                    (float) $this->input('longitude'),
                );

                if (!$withinBounds) {
                    $validator->errors()->add(
                        'location',
                        'This location is outside our supported area (Klang Valley only).'
                    );
                }
            },
        ];
    }
}
