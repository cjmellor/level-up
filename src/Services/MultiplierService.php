<?php

namespace LevelUp\Experience\Services;

use Illuminate\Support\Collection;

class MultiplierService
{
    public function __construct(
        private Collection $multipliers,
        private array $data = [],
    ) {
    }

    public function __invoke(int $points): int
    {
        /** @var \LevelUp\Experience\Contracts\Multiplier $multiplier */
        return $this->multipliers->reduce(
            callback: fn ($amount, $multiplier) => $multiplier->qualifies($this->getMultiplierData()->toArray())
                ? $amount * $multiplier->setMultiplier()
                : $amount,
            initial: $points
        );
    }

    protected function getMultiplierData(): Collection
    {
        return collect($this->data);
    }
}
