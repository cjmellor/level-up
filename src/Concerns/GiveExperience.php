<?php

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\ExperienceAudit;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Services\MultiplierService;

trait GiveExperience
{
    protected ?Collection $multiplierData = null;

    public function addPoints(
        int $amount,
        int $multiplier = null,
        string $type = AuditType::Add->value,
        string $reason = null
    ): Experience {
        /**
         * If the Multiplier Service is enabled, apply the Multipliers.
         */
        if (config(key: 'level-up.multiplier.enabled') && file_exists(filename: config(key: 'level-up.multiplier.path'))) {
            $amount = $this->getMultipliers(amount: $amount);
        }

        if ($multiplier) {
            $amount *= $multiplier;
        }

        /**
         * If the User does not have an Experience record, create one.
         */
        if ($this->experience()->doesntExist()) {
            $this->experience()->create(attributes: [
                'level_id' => (int) config(key: 'level-up.starting_level'),
                'experience_points' => $amount,
            ]);

            $this->dispatchEvent($amount, $type, $reason);

            return $this->experience;
        }

        /**
         * If the User does have an Experience record, update it.
         */
        if (config(key: 'level-up.level_cap.enabled') && $this->getLevel() >= config(key: 'level-up.level_cap.level') && ! (config(key: 'level-up.level_cap.points_continue'))) {
            return $this->experience;
        }

        $this->experience->increment(column: 'experience_points', amount: $amount);

        $this->dispatchEvent($amount, $type, $reason);

        return $this->experience;
    }

    protected function getMultipliers(int $amount): int
    {
        $multiplierService = app(MultiplierService::class, [
            'data' => $this->multiplierData ? $this->multiplierData->toArray() : [],
        ]);

        return $multiplierService(points: $amount);
    }

    public function experience(): HasOne
    {
        return $this->hasOne(related: Experience::class);
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

    public function getLevel(): int
    {
        return $this->experience->status->level;
    }

    public function deductPoints(int $amount): Experience
    {
        $this->experience->decrement(column: 'experience_points', amount: $amount);

        event(new PointsDecreased(pointsDecreasedBy: $amount, totalPoints: $this->experience->experience_points));

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

    public function withMultiplierData(array $data): static
    {
        $this->multiplierData = collect($data);

        return $this;
    }

    public function nextLevelAt(int $checkAgainst = null, bool $showAsPercentage = false): int
    {
        $nextLevel = Level::firstWhere(column: 'level', operator: '=', value: $checkAgainst ?? $this->getLevel() + 1);

        if ($nextLevel && $nextLevel->next_level_experience === null) {
            return 0;
        }

        $currentLevelExperience = Level::firstWhere(column: 'level', operator: '=', value: $this->getLevel())->next_level_experience;

        if ($showAsPercentage) {
            return (int) ((($this->getPoints() - $currentLevelExperience) / ($nextLevel->next_level_experience - $currentLevelExperience)) * 100);
        }

        return max(0, ($nextLevel->next_level_experience - $currentLevelExperience) - ($this->getPoints() - $currentLevelExperience));
    }

    public function getPoints(): int
    {
        return $this->experience->experience_points;
    }

    public function levelUp(): void
    {
        if (config(key: 'level-up.level_cap.enabled') && $this->getLevel() >= config(key: 'level-up.level_cap.level')) {
            return;
        }

        $nextLevel = Level::firstWhere(column: 'level', operator: $this->getLevel() + 1);

        $this->experience->status()->associate(model: $nextLevel);

        if (config(key: 'level-up.audit.enabled')) {
            $this->experienceHistory()->create(attributes: [
                'user_id' => $this->id,
                'points' => $this->getPoints(),
                'levelled_up' => true,
                'level_to' => $nextLevel->level,
                'type' => AuditType::LevelUp->value,
            ]);
        }

        $this->update(attributes: [
            'level_id' => $nextLevel->id,
        ]);

        event(new UserLevelledUp(user: $this, level: $this->getLevel()));
    }

    public function experienceHistory(): HasMany
    {
        return $this->hasMany(related: ExperienceAudit::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(related: Level::class);
    }
}
