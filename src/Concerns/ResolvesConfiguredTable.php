<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

trait ResolvesConfiguredTable
{
    abstract protected function configuredTableKey(): string;

    public function initializeResolvesConfiguredTable(): void
    {
        $this->setTable(config('level-up.tables.'.$this->configuredTableKey()));
    }
}
