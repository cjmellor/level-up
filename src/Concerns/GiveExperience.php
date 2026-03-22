<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
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
        $type ??= AuditType::Add->value;

        $levelClass = config(key: 'level-up.models.level');

        $lastLevel = $levelClass::orderByDesc(column: 'level')->first();
        throw_if(
            condition: isset($lastLevel->next_level_experience) && $amount > $lastLevel->next_level_experience,
            message: 'Points exceed the last level\'s experience points.',
        );

        if (config(key: 'level-up.multiplier.enabled') && file_exists(filename: config(key: 'level-up.multiplier.path'))) {
            $amount = $this->getMultipliers(amount: $amount);
        }

        throw_if($this->multiplierCondition instanceof Closure && is_null($multiplier), InvalidArgumentException::class, message: 'Multiplier is not set');

        if (isset($this->multiplierCondition) && ! ($this->multiplierCondition)()) {
            $multiplier = 1;
        }

        if ($multiplier) {
            $amount *= $multiplier;
        }

        $amount = $this->applyTierMultiplier($amount);

        if ($this->experience()->doesntExist()) {
            $startingLevel = config(key: 'level-up.starting_level');

            $level = $levelClass::query()
                ->where(column: 'next_level_experience', operator: '<=', value: $amount)
                ->whereNotNull(columns: 'next_level_experience')
                ->orderByDesc(column: 'next_level_experience')
                ->first();

            if (! $level) {
                $level = $levelClass::firstOrCreate(
                    ['level' => $startingLevel],
                    ['next_level_experience' => null]
                );
            }

            $this->experience()->create(attributes: [
                'level_id' => $level->id,
                'experience_points' => $amount,
            ]);

            $this->dispatchEvent($amount, $type, $reason);

            if ($level->level > $startingLevel) {
                for ($lvl = $startingLevel; $lvl <= $level->level; $lvl++) {
                    event(new UserLevelledUp(user: $this, level: $lvl));
                }
            }

            return $this->experience;
        }

        if ($this->levelCapExceedsUserLevel()) {
            return $this->experience;
        }

        $this->experience->increment(column: 'experience_points', amount: $amount);

        $this->dispatchEvent($amount, $type, $reason);

        return $this->experience;
    }

    public function experience(): HasOne
    {
        return $this->hasOne(related: config('level-up.models.experience'));
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
        throw_unless($this->experience()->exists(), Exception::class, 'User has no experience record.');

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
     * @throws Exception
     */
    public function setPoints(int $amount): Experience
    {
        throw_unless($this->experience()->exists(), Exception::class, message: 'User has no experience record.');

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

        $currentLevel = $levelClass::firstWhere(column: 'level', operator: '=', value: $this->getLevel());

        if (! $currentLevel) {
            return 0;
        }

        $currentLevelExperience = $currentLevel->next_level_experience ?? 0;
        $range = $nextLevel->next_level_experience - $currentLevelExperience;

        if ($showAsPercentage) {
            return $range > 0
                ? (int) ((($this->getPoints() - $currentLevelExperience) / $range) * 100)
                : 0;
        }

        return max(0, $range - ($this->getPoints() - $currentLevelExperience));
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

        throw_unless($level, InvalidArgumentException::class, "Level {$to} does not exist.");

        $previousLevel = $this->getLevel();

        $this->experience->status()->associate(model: $level);
        $this->experience->save();

        for ($lvl = $previousLevel + 1; $lvl <= $to; $lvl++) {
            event(new UserLevelledUp(user: $this, level: $lvl));
        }
    }

    protected function getMultipliers(int $amount): int
    {
        if (isset($this->multiplierCondition) && ! ($this->multiplierCondition)()) {
            return $amount;
        }

        $multiplierService = resolve(MultiplierService::class, [
            'data' => $this->multiplierData?->toArray() ?? [],
        ]);

        return $multiplierService(points: $amount);
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

    protected function applyTierMultiplier(int $amount): int
    {
        $tierMultipliers = config(key: 'level-up.tiers.multipliers');

        if (! config(key: 'level-up.tiers.enabled') || blank($tierMultipliers) || ! method_exists($this, 'getTier')) {
            return $amount;
        }

        $tierName = $this->getTier()?->name;

        if (! $tierName || ! isset($tierMultipliers[$tierName])) {
            return $amount;
        }

        return (int) round($amount * $tierMultipliers[$tierName]);
    }

    protected function levelCapExceedsUserLevel(): bool
    {
        return config(key: 'level-up.level_cap.enabled')
            && $this->getLevel() >= config(key: 'level-up.level_cap.level')
            && ! config(key: 'level-up.level_cap.points_continue');
    }
}
