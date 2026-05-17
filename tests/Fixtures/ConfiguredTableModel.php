<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

class ConfiguredTableModel extends Model
{
    use ResolvesConfiguredTable;

    protected function configuredTableKey(): string
    {
        return 'experiences';
    }
}
