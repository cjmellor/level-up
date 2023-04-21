<?php

namespace LevelUp\Experience\Services;

use Illuminate\Support\Collection;

readonly class MultiplierService
{
    public function __construct(private Collection $multipliers)
    {
        //
    }

    public function __invoke(int $points, array $data): int
    {
        /** @var \LevelUp\Experience\Contracts\Multiplier $multiplier */
        return $this->multipliers->reduce(
            callback: fn ($amount, $multiplier) => $multiplier->qualifies($data)
                ? $amount * $multiplier->setMultiplier()
                : $amount,
            initial: $points
        );
    }
}