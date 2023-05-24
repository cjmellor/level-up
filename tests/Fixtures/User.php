<?php

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;

class User extends Model
{
    use GiveExperience;
    use HasAchievements;

    protected $guarded = [];
}
