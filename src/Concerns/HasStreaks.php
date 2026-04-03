<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Events\StreakBroken;
use LevelUp\Experience\Events\StreakFrozen;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\StreakStarted;
use LevelUp\Experience\Events\StreakUnfroze;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Streak;

trait HasStreaks
{
    public function recordStreak(Activity $activity): void
    {
        if (! $this->hasStreakForActivity(activity: $activity)) {
            $this->startNewStreak($activity);

            return;
        }

        $lastActivity = $this->getStreakLastActivity($activity);

        $diffInDays = (int) ($lastActivity->activity_at->startOfDay()->diffInDays(today()));

        if ($lastActivity->frozen_until && now()->lessThan($lastActivity->frozen_until)) {
            return;
        }

        if ($diffInDays === 0) {
            return;
        }

        if ($diffInDays > 1) {
            $this->resetStreak($activity);

            event(new StreakBroken($this, $activity, $this->streaks()->whereBelongsTo(related: $activity)->first()));

            return;
        }

        $streak = $this->streaks()->whereBelongsTo(related: $activity);
        $streak->increment(column: 'count');
        $streak->update(values: ['activity_at' => now()]);

        event(new StreakIncreased($this, $activity, $streak->first()));
    }

    public function streaks(): HasMany
    {
        return $this->hasMany(related: config('level-up.models.streak'));
    }

    public function resetStreak(Activity $activity): void
    {
        if (config(key: 'level-up.archive_streak_history.enabled')) {
            $this->archiveStreak($activity);
        }

        $this->streaks()
            ->whereBelongsTo(related: $activity)
            ->update([
                'count' => 1,
                'activity_at' => now(),
            ]);
    }

    public function getCurrentStreakCount(Activity $activity): int
    {
        return $this->streaks()->whereBelongsTo(related: $activity)->first()?->count ?? 0;
    }

    public function hasStreakToday(Activity $activity): bool
    {
        $lastActivity = $this->getStreakLastActivity($activity);

        return $lastActivity?->activity_at?->isToday() ?? false;
    }

    public function freezeStreak(Activity $activity, ?int $days = null): bool
    {
        $days ??= $this->getFreezeDurationForTier();
        $frozenUntil = now()->addDays(value: $days)->startOfDay();

        $result = $this->getStreakLastActivity($activity)
            ?->update(['frozen_until' => $frozenUntil]) ?? false;

        if ($result) {
            event(new StreakFrozen(
                frozenStreakLength: $days,
                frozenUntil: $frozenUntil
            ));
        }

        return $result;
    }

    public function unFreezeStreak(Activity $activity): bool
    {
        $result = $this->getStreakLastActivity($activity)
            ?->update(['frozen_until' => null]) ?? false;

        if ($result) {
            event(new StreakUnfroze());
        }

        return $result;
    }

    public function isStreakFrozen(Activity $activity): bool
    {
        return $this->getStreakLastActivity($activity)?->frozen_until !== null;
    }

    protected function hasStreakForActivity(Activity $activity): bool
    {
        return $this->streaks()
            ->whereBelongsTo(related: $activity)
            ->exists();
    }

    protected function startNewStreak(Activity $activity): Model|Streak
    {
        $streak = $activity->streaks()
            ->updateOrCreate(attributes: [
                config(key: 'level-up.user.foreign_key', default: 'user_id') => $this->id,
                'activity_id' => $activity->id,
                'activity_at' => now(),
            ]);

        event(new StreakStarted($this, $activity, $streak));

        return $streak;
    }

    protected function getStreakLastActivity(Activity $activity): ?Streak
    {
        return $this->streaks()
            ->whereBelongsTo(related: $activity)
            ->latest(column: 'activity_at')
            ->first();
    }

    protected function getFreezeDurationForTier(): int
    {
        $default = (int) config(key: 'level-up.freeze_duration');

        if (! config(key: 'level-up.tiers.enabled') || ! method_exists($this, 'getTier')) {
            return $default;
        }

        $tierFreezeDays = config(key: 'level-up.tiers.streak_freeze_days');

        if (blank($tierFreezeDays)) {
            return $default;
        }

        $tierName = $this->getTier()?->name;

        return (int) ($tierFreezeDays[$tierName] ?? $default);
    }

    protected function archiveStreak(Activity $activity): void
    {
        $latestStreak = $this->getStreakLastActivity($activity);

        if (! $latestStreak) {
            return;
        }

        $streakHistoryClass = config(key: 'level-up.models.streak_history');

        $streakHistoryClass::create([
            config(key: 'level-up.user.foreign_key', default: 'user_id') => $this->id,
            'activity_id' => $activity->id,
            'count' => $latestStreak->count,
            'started_at' => $latestStreak->activity_at->subDays($latestStreak->count - 1),
            'ended_at' => $latestStreak->activity_at,
        ]);
    }
}
