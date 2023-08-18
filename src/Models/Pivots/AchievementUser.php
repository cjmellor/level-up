<?php

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AchievementUser extends Pivot
{
    public function scopeWithProgress(Builder $query, int $progress): Collection
    {
        return $query->where(column: 'progress', operator: $progress)->get();
    }
}
