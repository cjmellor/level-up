<?php

declare(strict_types=1);

namespace LevelUp\Experience\Metrics;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LevelUp\Experience\Contracts\RankingMetric;

class LevelMetric implements RankingMetric
{
    public function key(): string
    {
        return 'level';
    }

    public function label(): string
    {
        return 'Level';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereHas(
            relation: 'experience',
            callback: fn (EloquentBuilder $query): EloquentBuilder => $query->whereNotNull(columns: 'level_id'),
        );
    }

    public function scoreExpression(): Builder
    {
        $experienceModel = config(key: 'level-up.models.experience');
        $levelModel = config(key: 'level-up.models.level');

        $experiencesTable = (new $experienceModel())->getTable();
        $levelsTable = (new $levelModel())->getTable();

        return $experienceModel::query()
            ->select(columns: $levelsTable.'.level')
            ->join(
                table: $levelsTable,
                first: $experiencesTable.'.level_id',
                operator: '=',
                second: $levelsTable.'.id',
            )
            ->whereColumn(
                first: $experiencesTable.'.'.config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->take(value: 1)
            ->toBase();
    }
}
