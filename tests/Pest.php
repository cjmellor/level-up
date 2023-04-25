<?php

use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\TestCase;
use Plannr\Laravel\FastRefreshDatabase\Traits\FastRefreshDatabase;

uses(TestCase::class, FastRefreshDatabase::class)
    ->beforeEach(hook: function (): void {
        $this->user = new User();

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
        );
    })
    ->in(__DIR__);
