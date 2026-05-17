<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasChallenges;
use LevelUp\Experience\Concerns\HasStreaks;
use LevelUp\Experience\Concerns\HasTiers;

class UlidUser extends Model
{
    use GiveExperience;
    use HasAchievements;
    use HasChallenges;
    use HasStreaks;
    use HasTiers;
    use HasUlids;

    protected $table = 'users';

    protected $guarded = [];

    public function getForeignKey(): string
    {
        return 'user_id';
    }
}
