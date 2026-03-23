<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Models\Challenge;

class ChallengeCompleted
{
    use Dispatchable;

    public function __construct(
        public Challenge $challenge,
        public Model $user,
    ) {}
}
