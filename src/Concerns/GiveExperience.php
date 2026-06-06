<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Enums\TierDirection;
use LevelUp\Experience\Events\MultiplierApplied;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Events\UserTierUpdated;
use LevelUp\Experience\Models\Experience;

trait GiveExperience
{
    public function addPoints(
        int $amount,
        int|float|null $multiplier = null,
        ?string $type = null,
        ?string $reason = null
    ): Experience {
        throw_if($multiplier !== null && $multiplier <= 0, InvalidArgumentException::class, message: 'Multiplier must be greater than 0.');

        $type ??= AuditType::Add->value;

        $levelClass = config(key: 'level-up.models.level');

        return DB::transaction(function () use ($amount, $multiplier, $type, $reason, $levelClass): Experience {
            [$amount, $appliedMultipliers] = $this->resolveMultipliers($amount, $multiplier);

            if ($this->experience()->doesntExist()) {
                $startingLevel = config(key: 'level-up.starting_level');

                $level = $levelClass::query()
                    ->where(column: 'next_level_experience', operator: '<=', value: $amount)
                    ->whereNotNull(columns: 'next_level_experience')
                    ->orderByDesc(column: 'level')
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
        });
    }

    /**
     * @return HasOne<Experience, $this>
     */
    public function experience(): HasOne
    {
        /** @var class-string<Experience> $experienceClass */
        $experienceClass = config('level-up.models.experience');

        return $this->hasOne(related: $experienceClass);
    }

    public function getLevel(): int
    {
        return $this->experience?->status->level ?? 0;
    }

    /**
     * @return HasMany<\LevelUp\Experience\Models\ExperienceAudit, $this>
     */
    public function experienceHistory(): HasMany
    {
        /** @var class-string<\LevelUp\Experience\Models\ExperienceAudit> $auditClass */
        $auditClass = config('level-up.models.experience_audit');

        return $this->hasMany(related: $auditClass);
    }

    public function deductPoints(int $amount, ?string $reason = null): Experience
    {
        throw_unless($this->experience()->exists(), Exception::class, 'User has no experience record.');

        return DB::transaction(function () use ($amount, $reason): Experience {
            $this->experience->decrement(column: 'experience_points', amount: $amount);

            event(new PointsDecreased(
                pointsDecreasedBy: $amount,
                totalPoints: $this->experience->experience_points,
                reason: $reason,
                user: $this,
            ));

            return $this->experience;
        });
    }

    public function setPoints(int $amount): Experience
    {
        throw_unless($this->experience()->exists(), Exception::class, message: 'User has no experience record.');

        return DB::transaction(function () use ($amount): Experience {
            $this->experience->update(attributes: [
                'experience_points' => $amount,
            ]);

            $this->recalculateLevelFor($amount);
            $this->recalculateTierFor($amount);

            return $this->experience->refresh();
        });
    }

    public function nextLevelAt(?int $checkAgainst = null, bool $showAsPercentage = false): int
    {
        $levelClass = config(key: 'level-up.models.level');

        $nextLevel = $levelClass::firstWhere(column: 'level', operator: '=', value: $checkAgainst ?? $this->getLevel() + 1);

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
        return $this->experience->experience_points ?? 0;
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

    protected function recalculateLevelFor(int $points): void
    {
        $levelClass = config(key: 'level-up.models.level');
        $experience = $this->experience;

        if (! $experience) {
            return;
        }

        $query = $levelClass::query()
            ->where(column: 'next_level_experience', operator: '<=', value: $points)
            ->whereNotNull(columns: 'next_level_experience');

        if (config(key: 'level-up.level_cap.enabled')) {
            $query->where(column: 'level', operator: '<=', value: config(key: 'level-up.level_cap.level'));
        }

        $newLevel = $query
            ->orderByDesc(column: 'level')
            ->first()
            ?? $levelClass::firstWhere(column: 'level', operator: '=', value: config(key: 'level-up.starting_level'));

        if (! $newLevel || $newLevel->id === $experience->level_id) {
            return;
        }

        $previousLevel = $this->getLevel();

        $experience->status()->associate($newLevel);
        $experience->save();

        if ($newLevel->level > $previousLevel) {
            for ($lvl = $previousLevel + 1; $lvl <= $newLevel->level; $lvl++) {
                event(new UserLevelledUp(user: $this, level: $lvl));
            }
        }
    }

    protected function recalculateTierFor(int $points): void
    {
        if (! config(key: 'level-up.tiers.enabled')) {
            return;
        }

        $experience = $this->experience;

        if (! $experience) {
            return;
        }

        $tierClass = config(key: 'level-up.models.tier');
        $newTier = $tierClass::forPoints(points: $points);
        $previousTierId = $experience->tier_id;

        if ($newTier?->id === $previousTierId) {
            return;
        }

        $previousTier = $previousTierId ? $tierClass::find($previousTierId) : null;

        $direction = match (true) {
            $previousTier === null => TierDirection::Promoted,
            $newTier === null => TierDirection::Demoted,
            $newTier->experience > $previousTier->experience => TierDirection::Promoted,
            default => TierDirection::Demoted,
        };

        if ($direction === TierDirection::Demoted && ! config(key: 'level-up.tiers.demotion')) {
            return;
        }

        $experience->update(['tier_id' => $newTier?->id]);

        event(new UserTierUpdated(
            user: $this,
            previousTier: $previousTier,
            newTier: $newTier,
            direction: $direction,
        ));
    }

    /**
     * @return array{0: int, 1: Collection}
     */
    protected function resolveMultipliers(int $amount, int|float|null $inlineMultiplier): array
    {
        $appliedMultipliers = collect();

        if (! config(key: 'level-up.multiplier.enabled')) {
            if ($inlineMultiplier !== null) {
                $amount = (int) round($amount * $inlineMultiplier);
            }

            return [$amount, $appliedMultipliers];
        }

        $multiplierClass = config(key: 'level-up.models.multiplier');
        $appliedMultipliers = $multiplierClass::active()->forUser($this)->get();

        $allValues = $appliedMultipliers->pluck('multiplier')->map(fn ($v): float => (float) $v);

        if ($inlineMultiplier !== null) {
            $allValues->push((float) $inlineMultiplier);
        }

        $originalAmount = $amount;
        $strategy = config(key: 'level-up.multiplier.stack_strategy', default: 'compound');

        if ($allValues->isNotEmpty()) {
            $amount = $this->applyStackingStrategy($amount, $allValues, $strategy);
        }

        if ($appliedMultipliers->isNotEmpty() || $inlineMultiplier !== null) {
            event(new MultiplierApplied(
                user: $this,
                multipliers: $appliedMultipliers,
                originalAmount: $originalAmount,
                finalAmount: $amount,
                strategy: $strategy,
            ));
        }

        return [$amount, $appliedMultipliers];
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
        $auditData = $this->buildMultiplierAuditData($appliedMultipliers, $inlineMultiplier);

        event(new PointsIncreased(
            pointsAdded: $amount,
            totalPoints: $this->experience->experience_points,
            type: $type,
            reason: $reason,
            user: $this,
            multipliers: $auditData,
        ));
    }

    protected function buildMultiplierAuditData(?Collection $appliedMultipliers, int|float|null $inlineMultiplier): ?array
    {
        if ($appliedMultipliers?->isEmpty() && $inlineMultiplier === null) {
            return null;
        }

        $auditData = $appliedMultipliers
            ?->map(fn ($m): array => ['id' => $m->id, 'name' => $m->name, 'value' => (float) $m->multiplier])
            ->values()
            ->all() ?? [];

        if ($inlineMultiplier !== null) {
            $auditData[] = ['id' => null, 'name' => 'inline', 'value' => (float) $inlineMultiplier];
        }

        return $auditData ?: null;
    }

    protected function levelCapExceedsUserLevel(): bool
    {
        return config(key: 'level-up.level_cap.enabled')
            && $this->getLevel() >= config(key: 'level-up.level_cap.level')
            && ! config(key: 'level-up.level_cap.points_continue');
    }
}
