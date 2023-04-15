<?php

use LevelUp\Experience\Exceptions\LevelExistsException;
use LevelUp\Experience\Models\Level;

it(description: 'can create a level', closure: function () {
    $level = Level::add(level: 1, pointsToNextLevel: 100);

    expect($level->level)->toBe(expected: 1)
        ->and($level->next_level_experience)->toBe(expected: 100);

    $this->assertDatabaseHas(table: 'levels', data: [
        'level' => 1,
        'next_level_experience' => 100,
    ]);
});

it(description: 'throws an error if a level exists', closure: function () {
    Level::add(level: 1, pointsToNextLevel: 100);
    Level::add(level: 1, pointsToNextLevel: 100);
})->throws(exception: LevelExistsException::class, exceptionMessage: 'The level with number "1" already exists');
