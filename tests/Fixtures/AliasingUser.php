<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasChallenges;
use LevelUp\Experience\Concerns\HasStreaks;
use RuntimeException;

#[\Illuminate\Database\Eloquent\Attributes\Table(name: 'users')]
class AliasingUser extends Model
{
    use GiveExperience {
        experience as packageExperience;
        experienceHistory as packageExperienceHistory;
    }
    use HasAchievements;
    use HasChallenges {
        challenges as packageChallenges;
    }
    use HasFactory;
    use HasStreaks {
        streaks as packageStreaks;
    }

    protected $guarded = [];

    public function getForeignKey(): string
    {
        return 'user_id';
    }

    public function challenges(): never
    {
        throw new RuntimeException('Host-app challenges() must not be called by trait internals.');
    }

    public function streaks(): never
    {
        throw new RuntimeException('Host-app streaks() must not be called by trait internals.');
    }

    public function experience(): never
    {
        throw new RuntimeException('Host-app experience() must not be called by trait internals.');
    }

    public function experienceHistory(): never
    {
        throw new RuntimeException('Host-app experienceHistory() must not be called by trait internals.');
    }
}
