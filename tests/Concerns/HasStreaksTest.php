<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\StreakBroken;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\StreakStarted;
use LevelUp\Experience\Models\Activity;

use function Spatie\PestPluginTestTime\testTime;

uses()->group('streaks');

beforeEach(closure: fn () => $this->activity = Activity::factory()->create());

test(description: 'record a streak if one does not exist for the activity', closure: function () {
    Event::fake();

    $this->user->recordStreak($this->activity);

    Event::assertDispatched(
        event: StreakStarted::class,
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

    $this->user->recordStreak($this->activity);

    expect($this->activity->streaks)->toHaveCount(count: 1)
        ->and($this->activity->streaks->first()->count)->toBe(expected: 1)
        ->and($this->activity->streaks->first()->activity_at->isToday());

    // Now, simulate the record happening the next day and instead, been updated
    testTime()->addDay();

    $this->user->recordStreak($this->activity);

    Event::assertDispatched(
        event: StreakIncreased::class,
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

test(description: 'a User\'s streak is broken when they miss a day', closure: function () {
    Event::fake();

    $this->user->recordStreak($this->activity);
    expect(value: $this->activity->streaks)->toHaveCount(count: 1)
        ->and($this->activity->streaks->first()->count)->toBe(expected: 1)
        ->and($this->activity->streaks->first()->activity_at->isToday());

    // Simulate the activity happening again the next day
    testTime()->addDays();

    $this->user->recordStreak($this->activity);
    expect(value: $this->activity->streaks)->toHaveCount(count: 1)
        ->and($this->activity->fresh()->streaks->first()->count)->toBe(expected: 2)
        ->and($this->activity->streaks->first()->activity_at->isTomorrow());

    // Simulate the activity happening again the next day
    testTime()->addDays(2);

    $this->user->recordStreak($this->activity);
    expect(value: $this->activity->streaks)->toHaveCount(count: 1)
        ->and($this->activity->fresh()->streaks->first()->count)->toBe(expected: 1)
        ->and($this->activity->fresh()->streaks->first()->activity_at)->toBeCarbon(now());

    Event::assertDispatched(
        event: StreakBroken::class,
        callback: fn (StreakBroken $event): bool => $event->user->is($this->user)
            && $event->activity->is($this->activity)
            && $event->streak->activity_at->isToday()
            && $event->streak->count === 1
    );
});

test(description: 'the Users current streak count is correct', closure: function () {
    $this->user->recordStreak($this->activity);

    expect($this->user->streaks)->toHaveCount(count: 1)
        ->and($this->user->getCurrentStreakCount($this->activity))->toBe(1);
});

test(description: 'a User has a streak going', closure: function () {
    $this->user->recordStreak($this->activity);

    expect($this->user->hasStreakToday($this->activity))->toBeTrue();
});

test(description: 'a User\'s streak can be reset', closure: function () {
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

test(description: 'when a streak is broken, it is also archived for historical usage', closure: function () {
    // Simulate a 3 day streak
    // Day 1
    $this->user->recordStreak($this->activity);

    // Day 2
    testTime()->addDays();
    $this->user->recordStreak($this->activity);

    // Day 3
    testTime()->addDays();
    $this->user->recordStreak($this->activity);

    expect($this->user->streaks)->toHaveCount(count: 1)
        ->and($this->user->getCurrentStreakCount($this->activity))->toBe(3);

    // Now, break the streak
    testTime()->addDays(2);
    $this->user->recordStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(expected: 1);

    // Check the streak's history data is correct...
    $this->assertDatabaseHas(table: 'streak_histories', data: [
        'user_id' => $this->user->id,
        'activity_id' => $this->activity->id,
        'count' => 3,
        'started_at' => now()->subDays(value: 4),
        'ended_at' => now()->subDays(value: 2),
    ]);
});
