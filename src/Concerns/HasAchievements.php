<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\AchievementProgressionIncreased;
use LevelUp\Experience\Events\AchievementRevoked;
use LevelUp\Experience\Exceptions\TierRequirementNotMet;
use LevelUp\Experience\Models\Achievement;

trait HasAchievements
{
    /**
     * @throws Exception
     */
    public function grantAchievement(Achievement $achievement, ?int $progress = null): void
    {
        throw_if($progress > 100, Exception::class, message: 'Progress cannot be greater than 100');

        if (config(key: 'level-up.tiers.enabled') && $achievement->tier_id && method_exists($this, 'getTier')) {
            $userTier = $this->getTier();
            $requiredTier = $achievement->tier;

            throw_unless($requiredTier, Exception::class, message: sprintf(
                'Achievement "%s" references a tier that no longer exists.', $achievement->name,
            ));

            throw_unless(
                $userTier && $userTier->experience >= $requiredTier->experience,
                TierRequirementNotMet::handle(tierName: $requiredTier->name),
            );
        }

        throw_if($this->allAchievements()->find($achievement->id), Exception::class, message: 'User already has this Achievement');

        $this->achievements()->attach($achievement, [
            'progress' => $progress,
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

    public function incrementAchievementProgress(Achievement $achievement, int $amount = 1): int
    {
        $userAchievement = $this->achievements()->find($achievement->id);

        throw_unless($userAchievement, Exception::class, 'User does not have this Achievement. Grant it first before incrementing progress.');

        $newProgress = min(100, ($userAchievement->pivot->progress ?? 0) + $amount);

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

    public function achievementsWithSpecificProgress(int $progress): BelongsToMany
    {
        return $this->achievements()
            ->wherePivot(column: 'progress', operator: '>=', value: $progress);
    }

    public function secretAchievements(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.models.achievement'))
            ->withPivot(columns: 'progress')
            ->where('is_secret', true);
    }

    public function revokeAchievement(Achievement $achievement): void
    {
        throw_unless($this->allAchievements()->find($achievement->id), Exception::class, message: 'User does not have this Achievement');

        $this->achievements()->detach($achievement->id);

        event(new AchievementRevoked(achievement: $achievement, user: $this));
    }
}
