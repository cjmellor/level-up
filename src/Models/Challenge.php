<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use LevelUp\Experience\Contracts\ChallengeCondition;

class Challenge extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
        'rewards' => 'array',
        'auto_enroll' => 'boolean',
        'is_repeatable' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static array $conditionRules = [
        'points_earned' => ['amount'],
        'level_reached' => ['level'],
        'achievement_earned' => ['achievement_id'],
        'streak_count' => ['activity', 'count'],
        'tier_reached' => ['tier'],
        'custom' => ['class'],
    ];

    protected static array $rewardRules = [
        'points' => ['amount'],
        'achievement' => ['achievement_id'],
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.user.model'), table: 'challenge_user')
            ->using(config(key: 'level-up.models.challenge_user'))
            ->withPivot(columns: ['progress', 'completed_at'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (Challenge $challenge): void {
            $challenge->validateConditions();
            $challenge->validateRewards();
        });
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query
            ->where(fn (Builder $q): Builder => $q->whereNull(columns: 'starts_at')->orWhere(column: 'starts_at', operator: '<=', value: now()))
            ->where(fn (Builder $q): Builder => $q->whereNull(columns: 'expires_at')->orWhere(column: 'expires_at', operator: '>', value: now()));
    }

    #[Scope]
    protected function autoEnroll(Builder $query): void
    {
        $query->where(column: 'auto_enroll', operator: '=', value: true);
    }

    protected function validateConditions(): void
    {
        $this->validateEntries(
            entries: $this->conditions ?? [],
            rules: self::$conditionRules,
            label: 'Condition',
        );
    }

    protected function validateRewards(): void
    {
        $this->validateEntries(
            entries: $this->rewards ?? [],
            rules: self::$rewardRules,
            label: 'Reward',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, list<string>>  $rules
     */
    private function validateEntries(array $entries, array $rules, string $label): void
    {
        $lowerLabel = lcfirst($label);

        foreach ($entries as $index => $entry) {
            throw_unless(isset($entry['type']), InvalidArgumentException::class, "{$label} at index {$index} is missing a 'type' key.");

            $type = $entry['type'];

            if (! array_key_exists($type, $rules)) {
                $allowed = implode(', ', array_keys($rules));

                throw new InvalidArgumentException("Invalid {$lowerLabel} type '{$type}' at index {$index}. Allowed: {$allowed}.");
            }

            foreach ($rules[$type] as $requiredKey) {
                throw_unless(array_key_exists($requiredKey, $entry), InvalidArgumentException::class, "{$label} '{$type}' at index {$index} is missing required key '{$requiredKey}'.");
            }

            if ($type === 'custom' && isset($entry['class'])) {
                $class = $entry['class'];
                throw_if(! class_exists($class) || ! is_subclass_of($class, ChallengeCondition::class), InvalidArgumentException::class, "{$label} at index {$index}: class '{$class}' must exist and implement ChallengeCondition.");
            }
        }
    }
}
