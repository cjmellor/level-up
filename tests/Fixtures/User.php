<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasChallenges;
use LevelUp\Experience\Concerns\HasStreaks;
use LevelUp\Experience\Concerns\HasTiers;
use LevelUp\Experience\Tests\Fixtures\Factories\UserFactory;

class User extends Model
{
    use GiveExperience;
    use HasAchievements;
    use HasChallenges;
    use HasFactory;
    use HasStreaks;
    use HasTiers;

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
