<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Events\ChallengeEnrolled;
use LevelUp\Experience\Events\ChallengeUnenrolled;
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

        $existingPivot = $this->challenges()->where('challenge_id', $challenge->id)->first()?->pivot;

        if ($existingPivot && $existingPivot->completed_at !== null && $challenge->is_repeatable) {
            $this->challenges()->updateExistingPivot($challenge->id, [
                'progress' => $this->freshChallengeProgress($challenge),
                'completed_at' => null,
            ]);

            event(new ChallengeEnrolled(challenge: $challenge, user: $this));

            return;
        }

        throw_if(
            condition: $existingPivot !== null,
            exception: Exception::class,
            message: $existingPivot?->completed_at !== null
                ? 'This challenge is completed and not repeatable.'
                : 'User is already enrolled in this challenge.',
        );

        $this->challenges()->attach($challenge->id, [
            'progress' => $this->freshChallengeProgress($challenge),
        ]);

        event(new ChallengeEnrolled(challenge: $challenge, user: $this));
    }

    /**
     * @throws Exception
     */
    public function unenrollFromChallenge(Challenge $challenge): void
    {
        $pivot = $this->challenges()->where('challenge_id', $challenge->id)->first()?->pivot;

        throw_if(
            condition: ! $pivot,
            exception: Exception::class,
            message: 'User is not enrolled in this challenge.',
        );

        throw_if(
            condition: $pivot->completed_at !== null,
            exception: Exception::class,
            message: 'Cannot unenroll from a completed challenge.',
        );

        $this->challenges()->detach($challenge->id);

        event(new ChallengeUnenrolled(challenge: $challenge, user: $this));
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

        return $pivot?->getDecodedProgress();
    }

    public function getChallengeCompletionPercentage(Challenge $challenge): ?float
    {
        $progress = $this->getChallengeProgress(challenge: $challenge);

        if (empty($progress)) {
            return null;
        }

        $completed = count(array_filter($progress, fn (array $entry): bool => $entry['completed'] ?? false));

        return round(num: ($completed / count($progress)) * 100, precision: 1);
    }

    private function freshChallengeProgress(Challenge $challenge): string
    {
        return json_encode(value: resolve(ChallengeService::class)->initializeProgress(
            user: $this,
            challenge: $challenge,
        ));
    }
}
