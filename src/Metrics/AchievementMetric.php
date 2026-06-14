<?php

declare(strict_types=1);

namespace LevelUp\Experience\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Contracts\Windowable;

class AchievementMetric implements RankingMetric, Windowable
{
    public function key(): string
    {
        return 'achievements';
    }

    public function label(): string
    {
        return 'Achievements';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereHas(relation: 'allAchievements');
    }

    public function scoreExpression(): Builder
    {
        $pivotModel = config(key: 'level-up.models.achievement_user');

        return $pivotModel::query()
            ->toBase()
            ->selectRaw(expression: 'COUNT(*)')
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            );
    }

    public function windowedScoreExpression(CarbonInterface $start, ?CarbonInterface $end = null): Builder
    {
        $pivotModel = config(key: 'level-up.models.achievement_user');

        $query = $pivotModel::query()
            ->toBase()
            ->selectRaw(expression: 'SUM(1)')
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->where(column: 'created_at', operator: '>=', value: $start);

        if ($end instanceof CarbonInterface) {
            $query->where(column: 'created_at', operator: '<', value: $end);
        }

        return $query;
    }
}
