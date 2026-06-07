<?php

declare(strict_types=1);

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Services\LeagueService;

class LeagueEnrollmentListener
{
    public function __invoke(PointsIncreased $event): void
    {
        resolve(name: LeagueService::class)->enroll(user: $event->user);
    }
}
