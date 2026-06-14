<?php

declare(strict_types=1);

namespace LevelUp\Experience\Support;

use Carbon\CarbonImmutable;

final readonly class PeriodRange
{
    public function __construct(
        public CarbonImmutable $start,
        public ?CarbonImmutable $end = null,
    ) {}
}
