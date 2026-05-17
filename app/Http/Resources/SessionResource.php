<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'short_code'              => $this->short_code,
            'transport_mode'          => $this->transport_mode,
            'venue_type'              => $this->venue_type,
            'halal_filter'            => $this->halal_filter,
            'status'                  => $this->status,
            'session_confirmed_venue' => $this->session_confirmed_venue,
            'session_confirmed_at'    => $this->session_confirmed_at?->toIso8601String(),
            'expires_at'              => $this->expires_at?->toIso8601String(),
            'shareable_url'           => $this->shareable_url,
            'qr_code_url'             => null,
            'participant_count'       => $this->whenLoaded('participants', fn () => $this->participants->count()),
            'participants'            => ParticipantResource::collection($this->whenLoaded('participants')),
            'created_at'              => $this->created_at?->toIso8601String(),
        ];
    }
}
