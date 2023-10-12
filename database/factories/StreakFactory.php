<?php

namespace LevelUp\Experience\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Streak;
use LevelUp\Experience\Tests\Fixtures\User;

class StreakFactory extends Factory
{
    protected $model = Streak::class;

    public function definition(): array
    {
        return [
            config(key: 'level-up.user.foreign_key', default: 'user_id') => User::factory(),
            'activity_id' => Activity::factory(),
            'count' => 1,
            'activity_at' => now(),
        ];
    }
}
