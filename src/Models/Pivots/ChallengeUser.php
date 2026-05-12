<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

class ChallengeUser extends Pivot
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $casts = [
        'completed_at' => 'datetime',
        'progress' => 'array',
    ];

    protected function configuredTableKey(): string
    {
        return 'challenge_user';
    }
}
