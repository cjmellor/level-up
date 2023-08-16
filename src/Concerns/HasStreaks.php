<?php

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\StreakStarted;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Streak;

trait HasStreaks
{
    public function recordStreak(Activity $activity): void
    {
        // If the user doesn't have a streak for this activity, start a new one
        if (! $this->hasStreakForActivity(activity: $activity)) {
            $this->startNewStreak($activity);
        }

        $diffInDays = $this->getStreakLastActivity($activity)
            ->activity_at
            ->startOfDay()
            ->diffInDays(now()->startOfDay());

        if ($diffInDays === 0) {
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
            ->updateOrCreate([
                'user_id' => $this->id,
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

    public function resetStreak(Activity $activity): void
    {
        $this->streaks()
            ->whereBelongsTo(related: $activity)
            ->update(['count' => 1]);
    }

    //    public function getLongestStreak(Activity $activity): int
    //    {
    //        return $this->streaks()
    //            ->whereBelongsTo(related: $activity)
    //            ->max(column: 'count');
    //    }
}
