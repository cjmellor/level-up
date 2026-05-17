<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

class AchievementUser extends Pivot
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected function configuredTableKey(): string
    {
        return 'achievement_user';
    }
}
