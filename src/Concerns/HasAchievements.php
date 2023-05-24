<?php

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\AchievementProgressionIncreased;
use LevelUp\Experience\Models\Achievement;
use LevelUp\Experience\Models\Pivots\AchievementUser;

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

        $this->achievements()->attach($achievement, [
            'progress' => $progress ?? null,
        ]);

        $this->when(value: ($progress === null) || ($progress === 100), callback: fn (): ?array => event(new AchievementAwarded(achievement: $achievement, user: $this)));
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(related: Achievement::class)
            ->withPivot(columns: 'progress')
            ->where('is_secret', false)
            ->using(AchievementUser::class);
    }

    public function incrementAchievementProgress(Achievement $achievement, int $amount = 1)
    {
        $newProgress = min(100, ($this->achievements()->find($achievement->id)->pivot->progress ?? 0) + $amount);

        $this->achievements()->updateExistingPivot($achievement->id, attributes: ['progress' => $newProgress]);

        event(new AchievementProgressionIncreased(achievement: $achievement, user: $this, amount: $amount));

        return $newProgress;
    }

    public function allAchievements(): BelongsToMany
    {
        return $this->belongsToMany(related: Achievement::class)
            ->withPivot(columns: 'progress');
    }

    public function achievementsWithProgress(): BelongsToMany
    {
        return $this->belongsToMany(related: Achievement::class)
            ->withPivot(columns: 'progress')
            ->where('is_secret', false)
            ->wherePivotNotNull(column: 'progress');
    }

    public function secretAchievements(): BelongsToMany
    {
        return $this->belongsToMany(related: Achievement::class)
            ->withPivot(columns: 'progress')
            ->where('is_secret', true);
    }
}
