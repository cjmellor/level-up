<?php

declare(strict_types=1);

namespace LevelUp\Experience\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;

interface Windowable
{
    public function windowedScoreExpression(CarbonInterface $start, ?CarbonInterface $end = null): Builder;
}
