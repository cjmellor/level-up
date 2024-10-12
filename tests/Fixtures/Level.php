<?php

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasStreaks;
use LevelUp\Experience\Tests\Fixtures\Factories\UserFactory;

class Level extends \LevelUp\Experience\Models\Level
{
    public function extra_function(): string
    {
        return 'extra_function';
    }
}
