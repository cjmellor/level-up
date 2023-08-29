<?php

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
