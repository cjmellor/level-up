<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Enums\TierDirection;
use LevelUp\Experience\Models\Tier;

class UserTierUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly ?Tier $previousTier,
        public readonly ?Tier $newTier,
        public readonly TierDirection $direction,
    ) {}
}
