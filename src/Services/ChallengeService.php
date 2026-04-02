<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Contracts\ChallengeCondition;
use LevelUp\Experience\Events\ChallengeCompleted;
use LevelUp\Experience\Models\Challenge;

class ChallengeService
{
    /** @var array<int|string> */
    protected static array $evaluatingUsers = [];

    /**
     * @param  array<string>  $conditionTypes
     */
    public function evaluateForUser(Model $user, array $conditionTypes): void
    {
        if (in_array($user->id, self::$evaluatingUsers, strict: true)) {
            return;
        }

        if (! config()->boolean(key: 'level-up.challenges.enabled')) {
            return;
        }

        self::$evaluatingUsers[] = $user->id;

        try {
            $enrolledChallenges = $this->getEnrolledChallenges(user: $user, conditionTypes: $conditionTypes);

            $autoEnrollChallenges = $this->getAutoEnrollChallenges(
                user: $user,
                conditionTypes: $conditionTypes,
                excludeIds: $enrolledChallenges->pluck('id')->all(),
            );

            foreach ($autoEnrollChallenges as $challenge) {
                $this->enrollUser(user: $user, challenge: $challenge);
            }

            $allChallenges = $enrolledChallenges->merge($autoEnrollChallenges);

            $preloaded = $this->preloadConditionData(user: $user, challenges: $allChallenges);

            foreach ($allChallenges as $challenge) {
                $this->evaluateChallenge(user: $user, challenge: $challenge, preloaded: $preloaded);
            }
        } finally {
            self::$evaluatingUsers = array_filter(
                self::$evaluatingUsers,
                fn ($id): bool => $id !== $user->id,
            );
        }
    }

    public function initializeProgress(Model $user, Challenge $challenge, bool $useCurrentBaseline = true): array
    {
        $progress = [];

        foreach ($challenge->conditions as $index => $condition) {
            $entry = [
                'type' => $condition['type'],
                'completed' => false,
            ];

            if ($condition['type'] === 'points_earned') {
                $entry['baseline'] = $useCurrentBaseline && method_exists($user, 'getPoints')
                    ? $user->getPoints()
                    : 0;
            }

            $progress[$index] = $entry;
        }

        return $progress;
    }

    protected function getEnrolledChallenges(Model $user, array $conditionTypes): Collection
    {
        $challengeModel = config(key: 'level-up.models.challenge');

        return $challengeModel::query()
            ->active()
            ->whereHas('users', fn ($q) => $q
                ->where(column: config(key: 'level-up.user.foreign_key'), operator: '=', value: $user->id)
                ->whereNull(columns: 'challenge_user.completed_at')
            )
            ->get()
            ->filter(fn (Challenge $challenge): bool => $this->hasMatchingCondition(
                challenge: $challenge,
                conditionTypes: $conditionTypes,
            ));
    }

    protected function getAutoEnrollChallenges(Model $user, array $conditionTypes, array $excludeIds): Collection
    {
        $challengeModel = config(key: 'level-up.models.challenge');

        return $challengeModel::query()
            ->active()
            ->autoEnroll()
            ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->whereDoesntHave('users', fn ($q) => $q
                ->where(column: config(key: 'level-up.user.foreign_key'), operator: '=', value: $user->id)
            )
            ->get()
            ->filter(fn (Challenge $challenge): bool => $this->hasMatchingCondition(
                challenge: $challenge,
                conditionTypes: $conditionTypes,
            ));
    }

    protected function hasMatchingCondition(Challenge $challenge, array $conditionTypes): bool
    {
        return collect($challenge->conditions)
            ->contains(fn (array $condition): bool => in_array($condition['type'], $conditionTypes, strict: true));
    }

    protected function enrollUser(Model $user, Challenge $challenge): void
    {
        try {
            $challenge->users()->attach($user->id, [
                'progress' => json_encode(value: $this->initializeProgress(user: $user, challenge: $challenge)),
            ]);
        } catch (UniqueConstraintViolationException) {
            //
        }
    }

    protected function preloadConditionData(Model $user, Collection $challenges): array
    {
        $activityNames = $challenges
            ->flatMap(fn (Challenge $challenge): array => $challenge->conditions)
            ->where('type', 'streak_count')
            ->pluck('activity')
            ->filter()
            ->unique()
            ->all();

        $activityModel = config(key: 'level-up.models.activity');

        return [
            'achievement_ids' => method_exists($user, 'allAchievements')
                ? $user->allAchievements()->pluck('achievements.id')->all()
                : [],
            'activities' => $activityNames !== []
                ? $activityModel::whereIn('name', $activityNames)->get()->keyBy('name')
                : collect(),
        ];
    }

    protected function evaluateChallenge(Model $user, Challenge $challenge, array $preloaded = []): void
    {
        $pivot = $challenge->users()
            ->where(column: config(key: 'level-up.user.foreign_key'), operator: '=', value: $user->id)
            ->whereNull(columns: 'challenge_user.completed_at')
            ->first()
            ?->pivot;

        if (! $pivot) {
            return;
        }

        $progress = $pivot->getDecodedProgress() ?? $this->initializeProgress(user: $user, challenge: $challenge);

        if (empty($challenge->conditions)) {
            return;
        }

        $allComplete = true;

        foreach ($challenge->conditions as $index => $condition) {
            if (($progress[$index]['completed'] ?? false) === true) {
                continue;
            }

            $met = $this->checkCondition(user: $user, condition: $condition, progressEntry: $progress[$index] ?? [], preloaded: $preloaded);

            $progress[$index]['completed'] = $met;

            if (! $met) {
                $allComplete = false;
            }
        }

        $challenge->users()->updateExistingPivot($user->id, attributes: [
            'progress' => json_encode(value: $progress),
        ]);

        if ($allComplete) {
            $this->completeChallenge(user: $user, challenge: $challenge);
        }
    }

    protected function checkCondition(Model $user, array $condition, array $progressEntry, array $preloaded = []): bool
    {
        return match ($condition['type']) {
            'points_earned' => $this->checkPointsEarned(user: $user, condition: $condition, progressEntry: $progressEntry),
            'level_reached' => $this->checkLevelReached(user: $user, condition: $condition),
            'achievement_earned' => $this->checkAchievementEarned(condition: $condition, preloaded: $preloaded),
            'streak_count' => $this->checkStreakCount(user: $user, condition: $condition, preloaded: $preloaded),
            'tier_reached' => $this->checkTierReached(user: $user, condition: $condition),
            'custom' => $this->checkCustomCondition(user: $user, condition: $condition),
            default => false,
        };
    }

    protected function checkPointsEarned(Model $user, array $condition, array $progressEntry): bool
    {
        if (! method_exists($user, 'getPoints')) {
            return false;
        }

        $baseline = $progressEntry['baseline'] ?? 0;

        return ($user->getPoints() - $baseline) >= ($condition['amount'] ?? 0);
    }

    protected function checkLevelReached(Model $user, array $condition): bool
    {
        if (! method_exists($user, 'getLevel')) {
            return false;
        }

        return $user->getLevel() >= ($condition['level'] ?? 0);
    }

    protected function checkAchievementEarned(array $condition, array $preloaded = []): bool
    {
        return in_array($condition['achievement_id'] ?? 0, $preloaded['achievement_ids'] ?? [], strict: true);
    }

    protected function checkStreakCount(Model $user, array $condition, array $preloaded = []): bool
    {
        if (! method_exists($user, 'getCurrentStreakCount')) {
            return false;
        }

        $activities = $preloaded['activities'] ?? collect();
        $activity = $activities->get($condition['activity'] ?? '');

        if (! $activity) {
            return false;
        }

        return $user->getCurrentStreakCount(activity: $activity) >= ($condition['count'] ?? 0);
    }

    protected function checkTierReached(Model $user, array $condition): bool
    {
        if (! method_exists($user, 'isAtOrAboveTier')) {
            return false;
        }

        return $user->isAtOrAboveTier(name: $condition['tier'] ?? '');
    }

    protected function checkCustomCondition(Model $user, array $condition): bool
    {
        $class = $condition['class'] ?? null;

        if (! $class || ! class_exists($class)) {
            return false;
        }

        if (! is_subclass_of($class, ChallengeCondition::class)) {
            return false;
        }

        return app(abstract: $class)->check(user: $user, condition: $condition);
    }

    protected function completeChallenge(Model $user, Challenge $challenge): void
    {
        $affected = DB::table('challenge_user')
            ->where(config(key: 'level-up.user.foreign_key'), $user->id)
            ->where('challenge_id', $challenge->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);

        if ($affected === 0) {
            return;
        }

        $this->dispatchRewards(user: $user, challenge: $challenge);

        event(new ChallengeCompleted(challenge: $challenge, user: $user));

        if ($challenge->is_repeatable) {
            $this->resetChallenge(user: $user, challenge: $challenge);
        }
    }

    protected function dispatchRewards(Model $user, Challenge $challenge): void
    {
        foreach ($challenge->rewards as $reward) {
            match ($reward['type']) {
                'points' => $this->rewardPoints(user: $user, reward: $reward),
                'achievement' => $this->rewardAchievement(user: $user, reward: $reward),
                default => report(new Exception(
                    "Unknown challenge reward type '{$reward['type']}' for challenge #{$challenge->id}"
                )),
            };
        }
    }

    protected function rewardPoints(Model $user, array $reward): void
    {
        if (! method_exists($user, 'addPoints')) {
            return;
        }

        $user->addPoints(amount: $reward['amount'] ?? 0);
    }

    protected function rewardAchievement(Model $user, array $reward): void
    {
        if (! method_exists($user, 'grantAchievement')) {
            return;
        }

        $achievementModel = config(key: 'level-up.models.achievement');
        $achievement = $achievementModel::find($reward['achievement_id'] ?? 0);

        if (! $achievement) {
            return;
        }

        if ($user->allAchievements()->where('achievements.id', $achievement->id)->exists()) {
            return;
        }

        $user->grantAchievement(achievement: $achievement);
    }

    protected function resetChallenge(Model $user, Challenge $challenge): void
    {
        $challenge->users()->updateExistingPivot($user->id, attributes: [
            'progress' => json_encode(value: $this->initializeProgress(user: $user, challenge: $challenge)),
            'completed_at' => null,
        ]);
    }
}
