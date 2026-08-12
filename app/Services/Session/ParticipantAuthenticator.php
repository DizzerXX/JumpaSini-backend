<?php

namespace App\Services\Session;

use App\Models\Participant;
use App\Models\Session;

/**
 * Resolves "who is making this request" for the public session endpoints.
 *
 * Sessions are shared by short code, so the code alone proves nothing about identity.
 * A participant proves it is itself with the access_token issued once, when it joined.
 */
class ParticipantAuthenticator
{
    /**
     * Return the participant when it belongs to the session and the token matches,
     * otherwise null. Callers decide the status code (403/404) for the failure.
     */
    public function authenticate(Session $session, string $participantId, ?string $accessToken): ?Participant
    {
        if ($accessToken === null || $accessToken === '') {
            return null;
        }

        $participant = $session->participants->find($participantId);

        if ($participant === null || !$participant->access_token) {
            return null;
        }

        return hash_equals($participant->access_token, $accessToken) ? $participant : null;
    }
}
