<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Models\Challenge;

trait HasChallenges
{
    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.models.challenge'), table: 'challenge_user')
            ->using(config(key: 'level-up.models.challenge_user'))
            ->withPivot(columns: ['progress', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * @throws Exception
     */
    public function enrollInChallenge(Challenge $challenge): void
    {
        throw_if(
            condition: $this->challenges()->where('challenge_id', $challenge->id)->exists(),
            exception: Exception::class,
            message: 'User is already enrolled in this challenge.',
        );

        $this->challenges()->attach($challenge->id, [
            'progress' => json_encode(value: $this->initializeChallengeProgress(challenge: $challenge)),
        ]);
    }

    public function activeChallenges(): BelongsToMany
    {
        return $this->challenges()
            ->whereNull(columns: 'challenge_user.completed_at');
    }

    public function completedChallenges(): BelongsToMany
    {
        return $this->challenges()
            ->whereNotNull(columns: 'challenge_user.completed_at');
    }

    public function getChallengeProgress(Challenge $challenge): ?array
    {
        $pivot = $this->challenges()->where('challenge_id', $challenge->id)->first()?->pivot;

        if (! $pivot) {
            return null;
        }

        $progress = $pivot->progress;

        return is_string($progress) ? json_decode(json: $progress, associative: true) : $progress;
    }

    protected function initializeChallengeProgress(Challenge $challenge): array
    {
        $progress = [];

        foreach ($challenge->conditions as $index => $condition) {
            $entry = [
                'type' => $condition['type'],
                'completed' => false,
            ];

            if ($condition['type'] === 'points_earned' && method_exists($this, 'getPoints')) {
                $entry['baseline'] = $this->getPoints();
            }

            $progress[$index] = $entry;
        }

        return $progress;
    }
}
