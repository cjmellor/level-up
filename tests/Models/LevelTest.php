<?php

use LevelUp\Experience\Exceptions\LevelExistsException;
use LevelUp\Experience\Models\Level;

it(description: 'can create a level', closure: function (): void {
    $level = Level::add(level: 5, pointsToNextLevel: 5000);

    expect(value: $level->level)->toBe(expected: 5)
        ->and($level->next_level_experience)->toBe(expected: 5000);

    $this->assertDatabaseHas(table: 'levels', data: [
        'level' => 5,
        'next_level_experience' => 5000,
    ]);
});

it(description: 'throws an error if a level exists', closure: function (): void {
    Level::add(level: 1, pointsToNextLevel: 100);
    Level::add(level: 1, pointsToNextLevel: 100);
})->throws(exception: LevelExistsException::class, exceptionMessage: 'The level with number "1" already exists');
