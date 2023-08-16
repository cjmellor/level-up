<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\StreakStarted;
use LevelUp\Experience\Models\Activity;

uses()->group('streaks');

test(description: 'record a streak if one does not exist for the activity', closure: function () {
    Event::fake();

    $this->activity = Activity::factory()->createOne();

    $this->user->recordStreak($this->activity);

    Event::assertDispatched(event: StreakStarted::class,
        callback: fn (StreakStarted $event): bool => $event->user->is($this->user)
            && $event->activity->is($this->activity)
            && $event->streak->activity_at->isToday(),
    );

    expect($this->activity->streaks)->toHaveCount(count: 1);

    $this->assertDatabaseHas(table: 'streaks', data: [
        'user_id' => $this->user->id,
        'activity_id' => $this->activity->id,
        'count' => 1,
        'activity_at' => now(),
    ]);
});

test(description: 'if an activity happens more than once on the same day, nothing will happen', closure: function () {
    // First, create a streak record
    $this->activity = Activity::factory()->createOne();

    $this->user->recordStreak($this->activity);

    expect($this->activity->streaks)->toHaveCount(count: 1);

    // Now, simulate the same activity being recorded
    $this->user->recordStreak($this->activity);

    // ... there should still only be one streak record
    expect($this->activity->streaks)->toHaveCount(count: 1);

    // Finally, check the data hasn't changed
    $this->assertDatabaseHas(table: 'streaks', data: [
        'user_id' => $this->user->id,
        'activity_id' => $this->activity->id,
        'count' => 1,
        'activity_at' => now(),
    ]);
});

test(description: 'when a streak record exists, update the data', closure: function () {
    Event::fake();

    // First, create a streak record
    $this->activity = Activity::factory()->createOne();

    $this->user->recordStreak($this->activity);

    expect($this->activity->streaks)->toHaveCount(count: 1);

    // Now, simulate the record happening the next day and instead, been updated
    // Using Carbon::setTestNow() doesn't seem to work here
    // This is the equivalent of recording a streak the next day
    $this->activity->streaks->first()->update([
        'activity_at' => now()->addDay(),
    ]);

    $this->user->recordStreak($this->activity);

    Event::assertDispatched(event: StreakIncreased::class,
        callback: fn (StreakIncreased $event): bool => $event->user->is($this->user)
            && $event->activity->is($this->activity)
            && $event->streak->activity_at->isToday()
            && $event->streak->count === 2
    );

    // There should still only be one streak record
    expect($this->activity->streaks)->toHaveCount(count: 1);

    // Finally, check the data has been updated
    $this->assertDatabaseHas(table: 'streaks', data: [
        'user_id' => $this->user->id,
        'activity_id' => $this->activity->id,
        'count' => 2,
        'activity_at' => now(),
    ]);
});

test(description: 'the Users current streak count is correct', closure: function () {
    $this->activity = Activity::factory()->createOne();

    $this->user->recordStreak($this->activity);

    expect($this->user->streaks)->toHaveCount(count: 1)
        ->and($this->user->getCurrentStreakCount($this->activity))->toBe(1);
});

test(description: 'a User has a streak going', closure: function () {
    $this->activity = Activity::factory()->createOne();

    $this->user->recordStreak($this->activity);

    expect($this->user->hasStreakToday($this->activity))->toBeTrue();
});

// test the streak can be reset
test(description: 'a User\'s streak can be reset', closure: function () {
    $this->activity = Activity::factory()->createOne();

    $this->user->recordStreak($this->activity);

    expect($this->user->hasStreakToday($this->activity))->toBeTrue();

    // Mimic recording a streak the next day
    // Setting Carbon::setTestNow() doesn't seem to work
    $this->activity->streaks->first()->update([
        'count' => 2,
        'activity_at' => now()->addDay(),
    ]);

    $this->user->resetStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(expected: 1);
});
