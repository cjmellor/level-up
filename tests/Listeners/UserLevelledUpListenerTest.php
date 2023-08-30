<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Listeners\UserLevelledUpListener;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
    config()->set(key: 'level-up.audit.enabled', value: true);
});

it(description: 'adds audit data when a User level\'s up', closure: function () {
    $this->user->addPoints(100);

    expect($this->user)->experienceHistory->count()->toBe(expected: 2);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => 100,
        'levelled_up' => true,
        'level_to' => 2,
        'type' => 'level_up',
    ]);
});

test(description: 'the Event and Listener run when levelling up', closure: function () {
    Event::fake();

    $this->user->addPoints(100);

    Event::assertDispatched(event: UserLevelledUp::class);
    Event::assertListening(expectedEvent: UserLevelledUp::class, expectedListener: UserLevelledUpListener::class);
});
