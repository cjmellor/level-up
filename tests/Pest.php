<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Tests\Fixtures\UlidUser;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\Fixtures\UuidUser;
use LevelUp\Experience\Tests\TestCase;
use LevelUp\Experience\Tests\UlidUserTestCase;
use LevelUp\Experience\Tests\UuidUserTestCase;

$seedLevels = function (): Collection {
    $levels = Level::add(
        ['level' => 1, 'next_level_experience' => null],
        ['level' => 2, 'next_level_experience' => 100],
        ['level' => 3, 'next_level_experience' => 250],
        ['level' => 4, 'next_level_experience' => 400],
        ['level' => 5, 'next_level_experience' => 600],
    );

    return collect($levels)->keyBy('level');
};

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () use ($seedLevels): void {
        $this->user = new User;

        $this->user->fill(attributes: [
            'name' => 'Chris Mellor',
            'email' => 'chris@mellor.pizza',
            'password' => bcrypt(value: 'password'),
            'email_verified_at' => now(),
        ])->save();

        $this->levels = $seedLevels();
    })
    ->in(__DIR__.'/Concerns', __DIR__.'/Listeners', __DIR__.'/Models', __DIR__.'/Services');

uses(UuidUserTestCase::class, RefreshDatabase::class)
    ->beforeEach(function () use ($seedLevels): void {
        config()->set(key: 'level-up.multiplier.enabled', value: false);
        config()->set(key: 'level-up.tiers.enabled', value: false);

        $this->user = new UuidUser;

        $this->user->fill(attributes: [
            'name' => 'UUID User',
            'email' => 'uuid@example.test',
            'password' => bcrypt(value: 'password'),
            'email_verified_at' => now(),
        ])->save();

        $this->levels = $seedLevels();
    })
    ->in(__DIR__.'/Uuid');

uses(UlidUserTestCase::class, RefreshDatabase::class)
    ->beforeEach(function () use ($seedLevels): void {
        config()->set(key: 'level-up.multiplier.enabled', value: false);
        config()->set(key: 'level-up.tiers.enabled', value: false);

        $this->user = new UlidUser;

        $this->user->fill(attributes: [
            'name' => 'ULID User',
            'email' => 'ulid@example.test',
            'password' => bcrypt(value: 'password'),
            'email_verified_at' => now(),
        ])->save();

        $this->levels = $seedLevels();
    })
    ->in(__DIR__.'/Ulid');

expect()->extend(name: 'toBeCarbon', extend: function (string $expected, ?string $format = null): object {
    if ($format === null) {
        $format = str_contains($expected, ':')
            ? 'Y-m-d H:i:s'
            : 'Y-m-d';
    }

    expect($this->value?->format($format))->toBe($expected);

    return $this;
});
