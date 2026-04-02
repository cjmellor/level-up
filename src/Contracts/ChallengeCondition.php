<?php

declare(strict_types=1);

namespace LevelUp\Experience\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ChallengeCondition
{
    public function check(Model $user, array $condition): bool;
}
