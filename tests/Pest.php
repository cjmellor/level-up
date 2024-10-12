<?php

use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\TestCase;
use Plannr\Laravel\FastRefreshDatabase\Traits\FastRefreshDatabase;

uses(TestCase::class, FastRefreshDatabase::class)
    ->beforeEach(hook: function (): void {
        $this->user = new User;

        $this->user->fill(attributes: [
            'name' => 'Chris Mellor',
            'email' => 'chris@mellor.pizza',
            'password' => bcrypt(value: 'password'),
            'email_verified_at' => now(),
        ])->save();

        /**
         * Adds Levels to the database.
         */
        Level::add(
            ['level' => 1, 'next_level_experience' => null],
            ['level' => 2, 'next_level_experience' => 100],
            ['level' => 3, 'next_level_experience' => 250],
            ['level' => 4, 'next_level_experience' => 400],
            ['level' => 5, 'next_level_experience' => 600],
        );
    })
    ->in(__DIR__);

// A custom expectation to check if a Carbon instance matches a given string
// Stolen from https://github.com/spatie/pest-plugin-test-time
expect()->extend(name: 'toBeCarbon', extend: function (string $expected, ?string $format = null) {
    if ($format === null) {
        $format = str_contains($expected, ':')
            ? 'Y-m-d H:i:s'
            : 'Y-m-d';
    }

    expect($this->value?->format($format))->toBe($expected);

    return $this;
});
