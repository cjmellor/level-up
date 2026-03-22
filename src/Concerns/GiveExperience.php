<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\MultiplierApplied;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Models\Experience;

trait GiveExperience
{
    public function addPoints(
        int $amount,
        int|float|null $multiplier = null,
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

        $originalAmount = $amount;
        $appliedMultipliers = collect();

        $strategy = config(key: 'level-up.multiplier.stack_strategy', default: 'compound');

        if (config(key: 'level-up.multiplier.enabled')) {
            $multiplierClass = config(key: 'level-up.models.multiplier');
            $appliedMultipliers = $multiplierClass::active()->forUser($this)->get();

            $allValues = $appliedMultipliers->pluck('multiplier')->map(fn ($v) => (float) $v);

            if ($multiplier !== null) {
                $allValues->push((float) $multiplier);
            }

            if ($allValues->isNotEmpty()) {
                $amount = $this->applyStackingStrategy($amount, $allValues, $strategy);
            }

            if ($appliedMultipliers->isNotEmpty() || $multiplier !== null) {
                event(new MultiplierApplied(
                    user: $this,
                    multipliers: $appliedMultipliers,
                    originalAmount: $originalAmount,
                    finalAmount: $amount,
                    strategy: $strategy,
                ));
            }
        } elseif ($multiplier !== null) {
            $amount = (int) round($amount * $multiplier);
        }

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

            $this->load('experience');

            $this->dispatchEvent($amount, $type, $reason, $appliedMultipliers, $multiplier);

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

        $this->dispatchEvent($amount, $type, $reason, $appliedMultipliers, $multiplier);

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

    public function setPoints(int $amount): Experience
    {
        throw_unless($this->experience()->exists(), Exception::class, message: 'User has no experience record.');

        $this->experience->update(attributes: [
            'experience_points' => $amount,
        ]);

        return $this->experience;
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

    protected function applyStackingStrategy(int $amount, Collection $multiplierValues, string $strategy): int
    {
        return (int) round(match ($strategy) {
            'compound' => $amount * $multiplierValues->reduce(fn (float $carry, float $value): float => $carry * $value, 1.0),
            'additive' => $amount * $multiplierValues->sum(),
            'highest' => $amount * $multiplierValues->max(),
            default => throw new InvalidArgumentException("Unknown multiplier stack strategy: {$strategy}"),
        });
    }

    protected function dispatchEvent(int $amount, string $type, ?string $reason, ?Collection $appliedMultipliers = null, int|float|null $inlineMultiplier = null): void
    {
        $auditData = null;

        if ($appliedMultipliers?->isNotEmpty() || $inlineMultiplier !== null) {
            $auditData = [];

            if ($appliedMultipliers?->isNotEmpty()) {
                foreach ($appliedMultipliers as $m) {
                    $auditData[] = ['id' => $m->id, 'name' => $m->name, 'value' => (float) $m->multiplier];
                }
            }

            if ($inlineMultiplier !== null) {
                $auditData[] = ['id' => null, 'name' => 'inline', 'value' => (float) $inlineMultiplier];
            }
        }

        event(new PointsIncreased(
            pointsAdded: $amount,
            totalPoints: $this->experience->experience_points,
            type: $type,
            reason: $reason,
            user: $this,
            multipliers: $auditData,
        ));
    }

    protected function levelCapExceedsUserLevel(): bool
    {
        return config(key: 'level-up.level_cap.enabled')
            && $this->getLevel() >= config(key: 'level-up.level_cap.level')
            && ! config(key: 'level-up.level_cap.points_continue');
    }
}
