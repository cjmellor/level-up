<?php

namespace LevelUp\Experience\Concerns;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Services\MultiplierService;

trait GiveExperience
{
    protected ?Closure $multiplierCondition = null;

    protected ?Collection $multiplierData = null;

    public function addPoints(
        int $amount,
        ?int $multiplier = null,
        ?string $type = null,
        ?string $reason = null
    ): Experience {
        if ($type === null) {
            $type = AuditType::Add->value;
        }

        $levelClass = config(key: 'level-up.models.level');

        $lastLevel = $levelClass::orderByDesc(column: 'level')->first();
        throw_if(
            condition: isset($lastLevel->next_level_experience) && $amount > $lastLevel->next_level_experience,
            message: 'Points exceed the last level\'s experience points.',
        );

        /**
         * If the Multiplier Service is enabled, apply the Multipliers.
         */
        if (config(key: 'level-up.multiplier.enabled') && file_exists(filename: config(key: 'level-up.multiplier.path'))) {
            $amount = $this->getMultipliers(amount: $amount);
        }

        if ($this->multiplierCondition instanceof \Closure && is_null($multiplier)) {
            throw new InvalidArgumentException(message: 'Multiplier is not set');
        }

        if (isset($this->multiplierCondition) && ! ($this->multiplierCondition)()) {
            $multiplier = 1;
        }

        if ($multiplier) {
            $amount *= $multiplier;
        }

        /**
         * If the User does not have an Experience record, create one.
         */
        if ($this->experience()->doesntExist()) {
            $startingLevel = config(key: 'level-up.starting_level');

            // Find the appropriate level based on experience points
            $level = $levelClass::query()
                ->where(column: 'next_level_experience', operator: '<=', value: $amount)
                ->where(column: 'next_level_experience', operator: '!=', value: null)
                ->orderByDesc(column: 'next_level_experience')
                ->first();

            // If no qualifying level found, use starting level
            if (! $level) {
                $level = $levelClass::firstOrCreate(
                    ['level' => $startingLevel],
                    ['next_level_experience' => null]
                );
            }

            $experience = $this->experience()->create(attributes: [
                'level_id' => $level->id,
                'experience_points' => $amount,
            ]);

            $this->dispatchEvent($amount, $type, $reason);

            // Only dispatch UserLevelledUp events if the user is above the starting level
            if ($level->level > $startingLevel) {
                for ($lvl = $startingLevel; $lvl <= $level->level; $lvl++) {
                    Event::dispatch(event: new UserLevelledUp(user: $this, level: $lvl));
                }
            }

            return $this->experience;
        }

        /**
         * If the User does have an Experience record, update it.
         */
        if ($this->levelCapExceedsUserLevel()) {
            return $this->experience;
        }

        $this->experience->increment(column: 'experience_points', amount: $amount);

        $this->dispatchEvent($amount, $type, $reason);

        return $this->experience;
    }

    protected function getMultipliers(int $amount): int
    {
        if (isset($this->multiplierCondition) && ! ($this->multiplierCondition)()) {
            return $amount;
        }

        $multiplierService = app(abstract: MultiplierService::class, parameters: [
            'data' => $this->multiplierData ? $this->multiplierData->toArray() : [],
        ]);

        return $multiplierService(points: $amount);
    }

    public function experience(): HasOne
    {
        return $this->hasOne(related: config('level-up.models.experience'));
    }

    protected function dispatchEvent(int $amount, string $type, ?string $reason): void
    {
        event(new PointsIncreased(
            pointsAdded: $amount,
            totalPoints: $this->experience->experience_points,
            type: $type,
            reason: $reason,
            user: $this,
        ));
    }

    protected function levelCapExceedsUserLevel(): bool
    {
        return config(key: 'level-up.level_cap.enabled')
            && $this->getLevel() >= config(key: 'level-up.level_cap.level')
            && ! (config(key: 'level-up.level_cap.points_continue'));
    }

    public function getLevel(): int
    {
        return $this->experience?->status?->level ?? 0;
    }

    public function experienceHistory(): HasMany
    {
        return $this->hasMany(related: config('level-up.models.experience_audit'));
    }

    public function deductPoints(int $amount, ?string $reason = null): Experience
    {
        if ($this->experience()->doesntExist()) {
            return $this->experience;
        }

        $this->experience->decrement(column: 'experience_points', amount: $amount);

        event(new PointsDecreased(
            pointsDecreasedBy: $amount,
            totalPoints: $this->experience->experience_points,
            reason: $reason,
            user: $this,
        ));

        return $this->experience;
    }

    /**
     * @throws \Exception
     */
    public function setPoints(int $amount): Experience
    {
        if (! $this->experience()->exists()) {
            throw new Exception(message: 'User has no experience record.');
        }

        $this->experience->update(attributes: [
            'experience_points' => $amount,
        ]);

        return $this->experience;
    }

    public function withMultiplierData(array|callable $data): static
    {
        if ($data instanceof Closure) {
            $this->multiplierCondition = $data;
        } else {
            $this->multiplierData = collect(value: $data);
        }

        return $this;
    }

    public function nextLevelAt(?int $checkAgainst = null, bool $showAsPercentage = false): int
    {
        $levelClass = config(key: 'level-up.models.level');

        $nextLevel = $levelClass::firstWhere(column: 'level', operator: '=', value: is_null($checkAgainst) ? $this->getLevel() + 1 : $checkAgainst);

        if ($this->levelCapExceedsUserLevel()) {
            return 0;
        }

        if (! $nextLevel || $nextLevel->next_level_experience === null) {
            return 0;
        }

        $currentLevelExperience = $levelClass::firstWhere(column: 'level', operator: '=', value: $this->getLevel())->next_level_experience;

        if ($showAsPercentage) {
            return (int) ((($this->getPoints() - $currentLevelExperience) / ($nextLevel->next_level_experience - $currentLevelExperience)) * 100);
        }

        return max(0, ($nextLevel->next_level_experience - $currentLevelExperience) - ($this->getPoints() - $currentLevelExperience));
    }

    public function getPoints(): int
    {
        return $this->experience?->experience_points ?? 0;
    }

    public function levelUp(int $to): void
    {
        if (config(key: 'level-up.level_cap.enabled') && $this->getLevel() >= config(key: 'level-up.level_cap.level')) {
            return;
        }

        $levelClass = config(key: 'level-up.models.level');
        $level = $levelClass::firstWhere(column: 'level', operator: '=', value: $to);

        if ($level) {
            $this->experience->status()->associate(model: $level);
            $this->experience->save();
        }

        // TODO: In next major version, enforce strict behavior by throwing when level is missing.
        // if (! $level) {
        //     throw new InvalidArgumentException("Level {$to} does not exist");
        // }

        // Fire an event for each level gained
        for ($lvl = $this->getLevel(); $lvl <= $to; $lvl++) {
            event(new UserLevelledUp(user: $this, level: $lvl));
        }
    }
}
