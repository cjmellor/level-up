<?php

declare(strict_types=1);

namespace LevelUp\Experience\Metrics;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LevelUp\Experience\Contracts\RankingMetric;

class ExperienceMetric implements RankingMetric
{
    public function key(): string
    {
        return 'xp';
    }

    public function label(): string
    {
        return 'Experience';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereHas(
            relation: 'experience',
            callback: fn (EloquentBuilder $query): EloquentBuilder => $query->whereNotNull(columns: 'experience_points'),
        );
    }

    public function scoreExpression(): Builder
    {
        $experienceModel = config(key: 'level-up.models.experience');

        return $experienceModel::query()
            ->select(columns: 'experience_points')
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->latest()
            ->take(value: 1)
            ->toBase();
    }
}
