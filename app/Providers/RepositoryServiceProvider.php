<?php

namespace App\Providers;

use App\Repositories\AnalyticsRepository;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Repositories\Contracts\ParticipantRepositoryInterface;
use App\Repositories\Contracts\SessionRepositoryInterface;
use App\Repositories\Contracts\VenueHalalReportRepositoryInterface;
use App\Repositories\Contracts\VenueRepositoryInterface;
use App\Repositories\Contracts\VenueVoteRepositoryInterface;
use App\Repositories\ParticipantRepository;
use App\Repositories\SessionRepository;
use App\Repositories\VenueHalalReportRepository;
use App\Repositories\VenueRepository;
use App\Repositories\VenueVoteRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(ParticipantRepositoryInterface::class, ParticipantRepository::class);
        $this->app->bind(VenueRepositoryInterface::class, VenueRepository::class);
        $this->app->bind(VenueHalalReportRepositoryInterface::class, VenueHalalReportRepository::class);
        $this->app->bind(VenueVoteRepositoryInterface::class, VenueVoteRepository::class);
        $this->app->bind(AnalyticsRepositoryInterface::class, AnalyticsRepository::class);
    }
}
