<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AnalyticsRepositoryInterface
{
    public function overview(): array;

    public function paginateSessions(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function venueStats(): array;

    public function paginateHalalReports(array $filters, int $perPage = 20): LengthAwarePaginator;
}
