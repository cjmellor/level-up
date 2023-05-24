<?php

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AchievementUser extends Pivot
{
    public function scopeWithProgress($query, int $progress)
    {
        return $query->where('progress', $progress)->get();
    }
}
