<?php

declare(strict_types=1);

namespace LevelUp\Experience\Contracts;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

interface RankingMetric
{
    public function key(): string;

    public function label(): string;

    public function enabled(): bool;

    public function constrain(EloquentBuilder $query): EloquentBuilder;

    public function scoreExpression(): Builder;
}
