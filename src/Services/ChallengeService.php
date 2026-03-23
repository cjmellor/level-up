<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use LevelUp\Experience\Contracts\ChallengeCondition;
use LevelUp\Experience\Events\ChallengeCompleted;
use LevelUp\Experience\Models\Challenge;

class ChallengeService
{
    /**
     * @param  array<string>  $conditionTypes
     */
    public function evaluateForUser(Model $user, array $conditionTypes): void
    {
        if (! config(key: 'level-up.challenges.enabled')) {
            return;
        }

        $challengeModel = config(key: 'level-up.models.challenge');

        if ($challengeModel::query()->doesntExist()) {
            return;
        }

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

        foreach ($allChallenges as $challenge) {
            $this->evaluateChallenge(user: $user, challenge: $challenge);
        }
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
        foreach ($challenge->conditions as $condition) {
            if (in_array(needle: $condition['type'], haystack: $conditionTypes, strict: true)) {
                return true;
            }
        }

        return false;
    }

    protected function enrollUser(Model $user, Challenge $challenge): void
    {
        try {
            $challenge->users()->attach($user->id, [
                'progress' => json_encode(value: $this->initializeProgress(user: $user, challenge: $challenge, useCurrentBaseline: false)),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already enrolled via a concurrent event — safe to continue
        }
    }

    protected function initializeProgress(Model $user, Challenge $challenge, bool $useCurrentBaseline = true): array
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

    protected function evaluateChallenge(Model $user, Challenge $challenge): void
    {
        $pivot = $challenge->users()
            ->where(column: config(key: 'level-up.user.foreign_key'), operator: '=', value: $user->id)
            ->whereNull(columns: 'challenge_user.completed_at')
            ->first()
            ?->pivot;

        if (! $pivot) {
            return;
        }

        $rawProgress = $pivot->progress;
        $progress = is_string($rawProgress)
            ? json_decode(json: $rawProgress, associative: true)
            : ($rawProgress ?? $this->initializeProgress(user: $user, challenge: $challenge));

        if (empty($challenge->conditions)) {
            return;
        }

        $allComplete = true;

        foreach ($challenge->conditions as $index => $condition) {
            if (isset($progress[$index]['completed']) && $progress[$index]['completed'] === true) {
                continue;
            }

            $met = $this->checkCondition(user: $user, condition: $condition, progressEntry: $progress[$index] ?? []);

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

    protected function checkCondition(Model $user, array $condition, array $progressEntry): bool
    {
        return match ($condition['type']) {
            'points_earned' => $this->checkPointsEarned(user: $user, condition: $condition, progressEntry: $progressEntry),
            'level_reached' => $this->checkLevelReached(user: $user, condition: $condition),
            'achievement_earned' => $this->checkAchievementEarned(user: $user, condition: $condition),
            'streak_count' => $this->checkStreakCount(user: $user, condition: $condition),
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

    protected function checkAchievementEarned(Model $user, array $condition): bool
    {
        if (! method_exists($user, 'allAchievements')) {
            return false;
        }

        return $user->allAchievements()
            ->wherePivot('achievement_id', $condition['achievement_id'] ?? 0)
            ->exists();
    }

    protected function checkStreakCount(Model $user, array $condition): bool
    {
        if (! method_exists($user, 'getCurrentStreakCount')) {
            return false;
        }

        $activityModel = config(key: 'level-up.models.activity');
        $activity = $activityModel::where(column: 'name', operator: '=', value: $condition['activity'] ?? '')->first();

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

        $instance = app(abstract: $class);

        if (! $instance instanceof ChallengeCondition) {
            return false;
        }

        return $instance->check(user: $user, condition: $condition);
    }

    protected function completeChallenge(Model $user, Challenge $challenge): void
    {
        $challenge->users()->updateExistingPivot($user->id, attributes: [
            'completed_at' => now(),
        ]);

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
                default => null,
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

        try {
            $user->grantAchievement(achievement: $achievement);
        } catch (\Exception) {
            // Achievement may already be granted — silently skip
        }
    }

    protected function resetChallenge(Model $user, Challenge $challenge): void
    {
        $challenge->users()->updateExistingPivot($user->id, attributes: [
            'progress' => json_encode(value: $this->initializeProgress(user: $user, challenge: $challenge)),
            'completed_at' => null,
        ]);
    }
}
