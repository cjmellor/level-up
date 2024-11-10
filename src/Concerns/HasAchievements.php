<?php

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\AchievementProgressionIncreased;
use LevelUp\Experience\Events\AchievementRevoked;
use LevelUp\Experience\Models\Achievement;

trait HasAchievements
{
    /**
     * @throws \Exception
     */
    public function grantAchievement(Achievement $achievement, $progress = null): void
    {
        if ($progress > 100) {
            throw new Exception(message: 'Progress cannot be greater than 100');
        }

        if ($this->allAchievements()->find($achievement->id)) {
            throw new Exception(message: 'User already has this Achievement');
        }

        $this->achievements()->attach($achievement, [
            'progress' => $progress ?? null,
        ]);

        $this->when(value: ($progress === null) || ($progress === 100), callback: fn (): ?array => event(new AchievementAwarded(achievement: $achievement, user: $this)));
    }

    public function allAchievements(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.models.achievement'))
            ->withPivot(columns: 'progress');
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.models.achievement'))
            ->withTimestamps()
            ->withPivot(columns: 'progress')
            ->where('is_secret', false)
            ->using(config(key: 'level-up.models.achievement_user'));
    }

    public function incrementAchievementProgress(Achievement $achievement, int $amount = 1)
    {
        $newProgress = min(100, ($this->achievements()->find($achievement->id)->pivot->progress ?? 0) + $amount);

        $this->achievements()->updateExistingPivot($achievement->id, attributes: ['progress' => $newProgress]);

        event(new AchievementProgressionIncreased(achievement: $achievement, user: $this, amount: $amount));

        return $newProgress;
    }

    public function getUserAchievements(): Collection
    {
        return $this->achievements;
    }

    public function achievementsWithProgress(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.models.achievement'))
            ->withPivot(columns: 'progress')
            ->where('is_secret', false)
            ->wherePivotNotNull(column: 'progress');
    }

    public function secretAchievements(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.models.achievement'))
            ->withPivot(columns: 'progress')
            ->where('is_secret', true);
    }

    /**
     * Revoke an achievement from the user
     */
    public function revokeAchievement(Achievement $achievement): void
    {
        if (! $this->allAchievements()->find($achievement->id)) {
            throw new Exception(message: 'User does not have this Achievement');
        }

        $this->achievements()->detach($achievement->id);

        event(new AchievementRevoked(achievement: $achievement, user: $this));
    }
}
