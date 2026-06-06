<?php

declare(strict_types=1);

namespace LevelUp\Experience\Metrics;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Exceptions\MetricRequiresActivityException;
use LevelUp\Experience\Models\Activity;

class StreakMetric implements RankingMetric
{
    public function __construct(private readonly ?Activity $activity = null) {}

    public function key(): string
    {
        return 'streak';
    }

    public function label(): string
    {
        return 'Streak';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereHas(
            relation: 'streaks',
            callback: fn (EloquentBuilder $query): EloquentBuilder => $query->where(
                column: 'activity_id',
                operator: '=',
                value: $this->activity()->id,
            ),
        );
    }

    public function scoreExpression(): Builder
    {
        $streakModel = config(key: 'level-up.models.streak');

        return $streakModel::query()
            ->select(columns: 'count')
            ->where(column: 'activity_id', operator: '=', value: $this->activity()->id)
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->take(value: 1)
            ->toBase();
    }

    private function activity(): Activity
    {
        throw_unless($this->activity instanceof Activity, exception: MetricRequiresActivityException::forMetric($this));

        return $this->activity;
    }
}
