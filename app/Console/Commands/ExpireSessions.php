<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\SessionRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSessions extends Command
{
    protected $signature   = 'sessions:expire';
    protected $description = 'Mark all sessions past their expiry time as completed.';

    public function handle(SessionRepositoryInterface $sessionRepository): int
    {
        $count = $sessionRepository->markExpired();

        $this->info("Expired {$count} session(s).");
        Log::info("sessions:expire — marked {$count} session(s) as completed.");

        return Command::SUCCESS;
    }
}
