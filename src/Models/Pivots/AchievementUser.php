<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use LevelUp\Experience\Concerns\HasConfigurableIds;

class AchievementUser extends Pivot
{
    use HasConfigurableIds;
}
