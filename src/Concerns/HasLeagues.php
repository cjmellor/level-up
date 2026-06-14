<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Illuminate\Support\Collection;
use LevelUp\Experience\Models\Cohort;
use LevelUp\Experience\Models\Division;
use LevelUp\Experience\Services\LeagueService;
use LevelUp\Experience\Support\LeaderboardEntry;

trait HasLeagues
{
    public function currentDivision(): ?Division
    {
        return resolve(name: LeagueService::class)->divisionFor(user: $this);
    }

    public function currentCohort(): ?Cohort
    {
        return resolve(name: LeagueService::class)->cohortFor(user: $this);
    }

    /**
     * @return Collection<int, LeaderboardEntry>
     */
    public function cohortStandings(): Collection
    {
        return resolve(name: LeagueService::class)->standingsFor(user: $this);
    }
}
