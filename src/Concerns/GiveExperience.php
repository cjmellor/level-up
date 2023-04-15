<?php

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOne;
use LevelUp\Experience\Events\PointsIncreasedEvent;
use LevelUp\Experience\Models\Experience;

trait GiveExperience
{
    public function addPoints(int $amount): Experience
    {
        /**
         * If the User does not have an Experience record, create one.
         */
        if (! $this->experience()->exists()) {
            return $this->experience()->create(attributes: [
                'level_id' => (int) config(key: 'level-up.starting_level'),
                'experience_points' => $amount,
            ]);
        }

        /**
         * If the User does have an Experience record, update it.
         */
        $this->experience->increment(column: 'experience_points', amount: $amount);

        event(new PointsIncreasedEvent(pointsAdded: $amount, totalPoints: $this->experience->experience_points));

        return $this->experience;
    }

    public function experience(): HasOne
    {
        return $this->hasOne(Experience::class);
    }
}
