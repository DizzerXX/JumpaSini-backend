<?php

namespace App\Services\Session;

use App\Models\Participant;
use App\Models\Session;
use Illuminate\Http\Request;

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

    /**
     * Identify the caller of a read endpoint from request headers.
     *
     * Used by GET routes (votes, summary) so a participant can be told which way it
     * voted. Headers rather than query parameters: the token is a credential, and
     * query strings end up in access logs and browser history.
     *
     * Returns null for anonymous callers — these endpoints stay public, the identity
     * only enriches the response.
     */
    public function fromRequest(Session $session, Request $request): ?Participant
    {
        $participantId = $request->header('X-Participant-Id');
        $accessToken   = $request->header('X-Participant-Token');

        if (!$participantId || !$accessToken) {
            return null;
        }

        return $this->authenticate($session, $participantId, $accessToken);
    }
}
