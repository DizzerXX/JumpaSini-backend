<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'address'    => $this->address,
            'latitude'   => (float) $this->latitude,
            'longitude'  => (float) $this->longitude,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
