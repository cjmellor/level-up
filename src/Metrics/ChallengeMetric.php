<?php

declare(strict_types=1);

namespace LevelUp\Experience\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Contracts\Windowable;

class ChallengeMetric implements RankingMetric, Windowable
{
    public function key(): string
    {
        return 'challenges';
    }

    public function label(): string
    {
        return 'Challenges';
    }

    public function enabled(): bool
    {
        return config()->boolean(key: 'level-up.challenges.enabled');
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereHas(relation: 'challengeCompletions');
    }

    public function scoreExpression(): Builder
    {
        $completionModel = config(key: 'level-up.models.challenge_completion');

        return $completionModel::query()
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
        $completionModel = config(key: 'level-up.models.challenge_completion');

        $query = $completionModel::query()
            ->toBase()
            ->selectRaw(expression: 'SUM(1)')
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->where(column: 'completed_at', operator: '>=', value: $start);

        if ($end instanceof CarbonInterface) {
            $query->where(column: 'completed_at', operator: '<', value: $end);
        }

        return $query;
    }
}
