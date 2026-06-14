<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Contracts\RankingMetric;

class UserIdMetric implements RankingMetric
{
    public function __construct(private readonly bool $enabled = true) {}

    public function key(): string
    {
        return 'user-id';
    }

    public function label(): string
    {
        return 'User ID';
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query;
    }

    public function scoreExpression(): Builder
    {
        return DB::table('users', as: 'score_source')
            ->select(columns: 'score_source.id')
            ->whereColumn(first: 'score_source.id', operator: '=', second: 'users.id');
    }
}
