<?php

namespace App\Jobs;

use App\Models\Session;
use App\Repositories\Contracts\ParticipantRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Services\Midpoint\MidpointCalculatorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecalculateMidpointJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $sessionId) {}

    public function handle(
        SessionRepositoryInterface     $sessionRepository,
        ParticipantRepositoryInterface $participantRepository,
        MidpointCalculatorService      $calculator,
    ): void {
        $session = Session::find($this->sessionId);

        if ($session === null || $session->expires_at->isPast()) {
            return;
        }

        $participants = $participantRepository->findBySession($this->sessionId);

        if ($participants->isEmpty()) {
            return;
        }

        try {
            $result = $calculator->calculate($participants->all(), $session->transport_mode);
            $sessionRepository->updateMidpoint($this->sessionId, $result);
        } catch (\Throwable $e) {
            Log::error('RecalculateMidpointJob failed', [
                'session_id' => $this->sessionId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
