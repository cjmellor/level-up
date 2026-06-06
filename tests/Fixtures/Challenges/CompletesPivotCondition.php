<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures\Challenges;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Contracts\ChallengeCondition;

class CompletesPivotCondition implements ChallengeCondition
{
    public function check(Model $user, array $condition): bool
    {
        DB::table(config('level-up.tables.challenge_user'))
            ->where(config(key: 'level-up.user.foreign_key'), $user->getKey())
            ->update(['completed_at' => now()]);

        return true;
    }
}
