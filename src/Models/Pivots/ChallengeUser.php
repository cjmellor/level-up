<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChallengeUser extends Pivot
{
    protected $casts = [
        'progress' => 'array',
        'completed_at' => 'datetime',
    ];
}
