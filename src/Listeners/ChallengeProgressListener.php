<?php

declare(strict_types=1);

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Events\UserTierUpdated;
use LevelUp\Experience\Services\ChallengeService;

class ChallengeProgressListener
{
    public function __invoke(PointsIncreased|AchievementAwarded|StreakIncreased|UserLevelledUp|UserTierUpdated $event): void
    {
        if (! config()->boolean(key: 'level-up.challenges.enabled')) {
            return;
        }

        $user = $event->user;
        $conditionTypes = $this->mapEventToConditionTypes(event: $event);

        try {
            app(abstract: ChallengeService::class)->evaluateForUser(user: $user, conditionTypes: $conditionTypes);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function mapEventToConditionTypes(PointsIncreased|AchievementAwarded|StreakIncreased|UserLevelledUp|UserTierUpdated $event): array
    {
        $types = match (true) {
            $event instanceof PointsIncreased => ['points_earned'],
            $event instanceof UserLevelledUp => ['level_reached'],
            $event instanceof AchievementAwarded => ['achievement_earned'],
            $event instanceof StreakIncreased => ['streak_count'],
            $event instanceof UserTierUpdated => ['tier_reached'],
        };

        $types[] = 'custom';

        return $types;
    }
}
