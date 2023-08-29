<?php

use LevelUp\Experience\Exceptions\LevelExistsException;
use LevelUp\Experience\Models\Level;

uses()->group('levels');

it(description: 'can create a level', closure: function (): void {
    $level = Level::add([
        'level' => 6,
        'next_level_experience' => 750,
    ]);

    expect(value: $level[0]->level)->toBe(expected: 6);

    $this->assertDatabaseHas(table: 'levels', data: [
        'level' => 6,
        'next_level_experience' => 750,
    ]);
});

it(description: 'throws an error if a level exists', closure: function (): void {
    Level::add(level: 1, pointsToNextLevel: 100);
    Level::add(level: 1, pointsToNextLevel: 100);
})->throws(exception: LevelExistsException::class, exceptionMessage: 'The level with number "1" already exists');
