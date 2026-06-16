<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use LevelUp\Experience\Models\Challenge;

uses()->group('challenges');

test(description: 'the ledger migration backfills a completion for each already-completed pivot', closure: function (): void {
    $challenge = Challenge::factory()->create();
    $this->user->challenges()->attach($challenge->id, ['completed_at' => now()]);

    expect($this->user->challengeCompletions()->count())->toBe(expected: 0);

    Schema::drop(config('level-up.tables.challenge_completions'));
    $migration = include __DIR__.'/../../database/migrations/create_challenge_completions_table.php.stub';
    $migration->up();

    expect($this->user->challengeCompletions()->count())->toBe(expected: 1)
        ->and($this->user->challengeCompletions()->first()->challenge_id)->toEqual($challenge->id)
        ->and($this->user->challengeCompletions()->first()->completed_at)->not->toBeNull();
});

test(description: 'the ledger migration leaves never-completed pivots out of the backfill', closure: function (): void {
    $challenge = Challenge::factory()->create();
    $this->user->challenges()->attach($challenge->id);
    Schema::drop(config('level-up.tables.challenge_completions'));
    $migration = include __DIR__.'/../../database/migrations/create_challenge_completions_table.php.stub';
    $migration->up();

    expect($this->user->challengeCompletions()->count())->toBe(expected: 0);
});
