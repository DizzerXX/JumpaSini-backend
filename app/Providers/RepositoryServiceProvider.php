<?php

namespace App\Providers;

use App\Repositories\Contracts\ParticipantRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\VenueHalalReportRepositoryInterface;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Repositories\ParticipantRepository;
use App\Repositories\SessionRepository;
use App\Repositories\VenueHalalReportRepository;
use App\Repositories\VenueRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, ParticipantRepository::class);
        $this->app->bind(VenueRepositoryInterface::class, VenueRepository::class);
        $this->app->bind(VenueHalalReportRepositoryInterface::class, VenueHalalReportRepository::class);
    }
}
