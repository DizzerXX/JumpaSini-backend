<?php

namespace App\Console\Commands;

use App\Jobs\AutoConfirmVenueJob;
use App\Models\Session;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoConfirmSessions extends Command
{
    protected $signature   = 'sessions:auto-confirm';
    protected $description = 'Auto-confirm the winning venue for sessions whose voting period has ended.';

    public function handle(): int
    {
        // Reliable fallback for QUEUE_CONNECTION=sync (Render free tier).
        // With a Redis queue, AutoConfirmVenueJob handles this via delayed dispatch.
        $sessions = Session::whereNotNull('voting_ends_at')
            ->where('voting_ends_at', '<=', now())
            ->whereNull('voting_confirmed_venue')
            ->where('status', '!=', 'completed')
            ->get();

        foreach ($sessions as $session) {
            AutoConfirmVenueJob::dispatchSync($session->id);
        }

        $count = $sessions->count();
        $this->info("Auto-confirmed {$count} session(s).");
        Log::info("sessions:auto-confirm — processed {$count} session(s).");

        return Command::SUCCESS;
    }
}
