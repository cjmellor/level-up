<?php

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
    })
    ->in(__DIR__);
