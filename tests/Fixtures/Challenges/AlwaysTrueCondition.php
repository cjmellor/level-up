<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures\Challenges;

use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Contracts\ChallengeCondition;

class AlwaysTrueCondition implements ChallengeCondition
{
    public function check(Model $user, array $condition): bool
    {
        return true;
    }
}
