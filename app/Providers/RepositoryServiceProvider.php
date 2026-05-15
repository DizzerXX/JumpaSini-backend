<?php

namespace App\Providers;

use App\Repositories\Contracts\ParticipantRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\ParticipantRepository;
use App\Repositories\SessionRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, ParticipantRepository::class);
    }
}
