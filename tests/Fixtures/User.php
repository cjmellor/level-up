<?php

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasStreaks;
use LevelUp\Experience\Tests\Fixtures\Factories\UserFactory;

class User extends Model
{
    use GiveExperience;
    use HasAchievements;
    use HasFactory;
    use HasStreaks;

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
