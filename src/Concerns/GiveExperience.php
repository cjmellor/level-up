<?php

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LevelUp\Experience\Events\PointsDecreasedEvent;
use LevelUp\Experience\Events\PointsIncreasedEvent;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Level;

trait GiveExperience
{
    public function addPoints(int $amount, int $multiplier = null, array $data = []): Experience
    {
        /**
         * If the Multiplier Service is enabled, apply the Multipliers.
         */
        if (config(key: 'level-up.multiplier.enabled')) {
            $amount = $this->getMultipliers(amount: $amount, data: $data);
        }

        if ($multiplier) {
            $amount *= $multiplier;
        }

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
        return $this->hasOne(related: Experience::class);
    }

    public function deductPoints(int $amount): Experience
    {
        $this->experience->decrement(column: 'experience_points', amount: $amount);

        event(new PointsDecreasedEvent(pointsDecreasedBy: $amount, totalPoints: $this->experience->experience_points));

        return $this->experience;
    }

    public function setPoints(int $amount): Experience
    {
        $this->experience->update(['experience_points' => $amount]);

        return $this->experience;
    }

    public function getPoints(): int
    {
        return $this->experience->experience_points;
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(related: Level::class);
    }

    protected function getMultipliers(int $amount, array $data): int
    {
        return app(MultiplierService::class)(points: $amount, data: $data);
    }
}
