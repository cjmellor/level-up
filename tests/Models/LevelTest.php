<?php

use LevelUp\Experience\Exceptions\LevelExistsException;
use LevelUp\Experience\Models\Level;

uses()->group('levels');

it(description: 'can create a level without a title', closure: function (): void {
    $level = Level::add([
        'level' => 4,
        'next_level_experience' => 500,
    ]);

    expect(value: $level[0]->level)->toBe(expected: 4);

    $this->assertDatabaseHas(table: 'levels', data: [
        'level' => 4,
        'next_level_experience' => 500,
        'title' => null,
    ]);
});

it(description: 'can create a level with a title', closure: function (): void {
    $level = Level::add([
        'level' => 4,
        'next_level_experience' => 500,
        'title' => 'Monkey Juggler',
    ]);

    expect(value: $level[0]->level)->toBe(expected: 4);

    $this->assertDatabaseHas(table: 'levels', data: [
        'level' => 4,
        'next_level_experience' => 500,
        'title' => 'Monkey Juggler',
    ]);
});

it(description: 'throws an error if a level exists', closure: function (): void {
    Level::add(level: 1, pointsToNextLevel: 100);
    Level::add(level: 1, pointsToNextLevel: 100);
})->throws(exception: LevelExistsException::class, exceptionMessage: 'The level with number "1" already exists');
