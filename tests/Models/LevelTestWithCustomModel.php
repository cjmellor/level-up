<?php

use LevelUp\Experience\Exceptions\LevelExistsException;
use LevelUp\Experience\Tests\Fixtures\User;

uses()->group('levels');

beforeEach(function (): void {
    config()->set('level-up.models.level', \LevelUp\Experience\Tests\Fixtures\Level::class);
});


it(description: 'is a custom model', closure: function (): void {
    $levelClass = config('level-up.models.level');

    $level = $levelClass::add([
        'level' => 6,
        'next_level_experience' => 750,
    ]);

    expect(value: $level[0])->toBeInstanceOf($levelClass);
    expect(value: $level[0]->extra_function())->toBe(expected: 'extra_function');
});

it(description: 'can create a level with custom model', closure: function (): void {

    $level = config('level-up.models.level')::add([
        'level' => 6,
        'next_level_experience' => 750,
    ]);

    expect(value: $level[0]->level)->toBe(expected: 6);

    $this->assertDatabaseHas(table: 'levels', data: [
        'level' => 6,
        'next_level_experience' => 750,
    ]);
});

it(description: 'throws an error if a level exists with custom model', closure: function (): void {
    config('level-up.models.level')::add(level: 1, pointsToNextLevel: 100);
    config('level-up.models.level')::add(level: 1, pointsToNextLevel: 100);
})->throws(exception: LevelExistsException::class, exceptionMessage: 'The level with number "1" already exists');
