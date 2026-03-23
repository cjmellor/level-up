<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Models\Challenge;
use LevelUp\Experience\Services\ChallengeService;

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
            condition: $challenge->starts_at && $challenge->starts_at->isFuture(),
            exception: Exception::class,
            message: 'This challenge has not started yet.',
        );

        throw_if(
            condition: $challenge->expires_at && $challenge->expires_at->isPast(),
            exception: Exception::class,
            message: 'This challenge has expired.',
        );

        throw_if(
            condition: $this->challenges()->where('challenge_id', $challenge->id)->exists(),
            exception: Exception::class,
            message: 'User is already enrolled in this challenge.',
        );

        $this->challenges()->attach($challenge->id, [
            'progress' => json_encode(value: app(abstract: ChallengeService::class)->initializeProgress(
                user: $this,
                challenge: $challenge,
            )),
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
}
