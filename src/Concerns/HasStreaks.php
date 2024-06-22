<?php

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\StreakBroken;
use LevelUp\Experience\Events\StreakFrozen;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\StreakStarted;
use LevelUp\Experience\Events\StreakUnfroze;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Streak;
use LevelUp\Experience\Models\StreakHistory;

trait HasStreaks
{
    public function recordStreak(Activity $activity): void
    {
        // If the user doesn't have a streak for this activity, start a new one
        if (! $this->hasStreakForActivity(activity: $activity)) {
            $this->startNewStreak($activity);

            return;
        }

        $diffInDays = intval($this->getStreakLastActivity($activity)
            ->activity_at
            ->startOfDay()
            ->diffInDays(now()->startOfDay()));

        // Checking to see if the streak is frozen
        if ($this->getStreakLastActivity($activity)->frozen_until && now()->lessThan($this->getStreakLastActivity($activity)->frozen_until)) {
            return;
        }

        if ($diffInDays === 0) {
            return;
        }

        // Check to see if the streak was broken
        if ($diffInDays > 1) {
            $this->resetStreak($activity);

            event(new StreakBroken($this, $activity, $this->streaks()->first()));

            return;
        }

        if ($diffInDays === 1) {
            $streak = $this->streaks()->whereBelongsTo(related: $activity);
            $streak->increment(column: 'count');
            $streak->update(values: ['activity_at' => now()]);

            event(new StreakIncreased($this, $activity, $streak->first()));
        } else {
            $this->startNewStreak($activity);
        }
    }

    protected function hasStreakForActivity(Activity $activity): bool
    {
        return $this->streaks()
            ->whereBelongsTo(related: $activity)
            ->exists();
    }

    public function streaks(): HasMany
    {
        return $this->hasMany(related: Streak::class);
    }

    protected function startNewStreak(Activity $activity): Model|Streak
    {
        $streak = $activity->streaks()
            ->updateOrCreate(attributes: [
                config(key: 'level-up.streaks.foreign_key', default: 'user_id') => $this->id,
                'activity_id' => $activity->id,
                'activity_at' => now(),
            ]);

        event(new StreakStarted($this, $activity, $streak));

        return $streak;
    }

    protected function getStreakLastActivity(Activity $activity): Streak
    {
        return $this->streaks()
            ->whereBelongsTo(related: $activity)
            ->latest(column: 'activity_at')
            ->first();
    }

    public function resetStreak(Activity $activity): void
    {
        // Archive the streak
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

    protected function archiveStreak(Activity $activity): void
    {
        $latestStreak = $this->getStreakLastActivity($activity);

        StreakHistory::create([
            config(key: 'level-up.streaks.foreign_key', default: 'user_id') => $this->id,
            'activity_id' => $activity->id,
            'count' => $latestStreak->count,
            'started_at' => $latestStreak->activity_at->subDays($latestStreak->count - 1),
            'ended_at' => $latestStreak->activity_at,
        ]);
    }

    public function getCurrentStreakCount(Activity $activity): int
    {
        return $this->streaks()->whereBelongsTo(related: $activity)->first()
            ? $this->streaks()->whereBelongsTo(related: $activity)->first()->count
            : 0;
    }

    public function hasStreakToday(Activity $activity): bool
    {
        return $this->getStreakLastActivity($activity)
            ->activity_at
            ->isToday();
    }

    public function freezeStreak(Activity $activity, ?int $days = null): bool
    {
        $days = $days ?? config(key: 'level-up.freeze_duration');

        Event::dispatch(new StreakFrozen(
            frozenStreakLength: $days,
            frozenUntil: now()->addDays(value: $days)->startOfDay()
        ));

        return $this->getStreakLastActivity($activity)
            ->update(['frozen_until' => now()->addDays(value: $days)->startOfDay()]);
    }

    public function unFreezeStreak(Activity $activity): bool
    {
        Event::dispatch(new StreakUnfroze());

        return $this->getStreakLastActivity($activity)
            ->update(['frozen_until' => null]);
    }

    public function isStreakFrozen(Activity $activity): bool
    {
        return ! is_null($this->getStreakLastActivity($activity)->frozen_until);
    }
}
