<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\AchievementProgressionIncreased;
use LevelUp\Experience\Models\Achievement;

beforeEach(closure: fn (): Achievement => $this->achievement = Achievement::factory()->create());

uses()->group('achievements');

test(description: 'a User can earn an Achievement', closure: function (): void {
    $this->user->grantAchievement($this->achievement);

    expect($this->user)->achievements->toHaveCount(1);

    $this->assertDatabaseHas(table: 'achievement_user', data: [
        'user_id' => $this->user->id,
        'achievement_id' => $this->achievement->id,
    ]);
});

test(description: 'a User can earn an Achievement with progress', closure: function (): void {
    $this->user->grantAchievement($this->achievement, 50);

    expect($this->user)->achievements->toHaveCount(1);

    $this->assertDatabaseHas(table: 'achievement_user', data: [
        'user_id' => $this->user->id,
        'achievement_id' => $this->achievement->id,
        'progress' => 50,
    ]);
});

it(description: 'throws an exception if the progress of an Achievement is greater than 100')
    ->defer(fn () => $this->user->grantAchievement($this->achievement, 101))
    ->throws(exception: Exception::class, exceptionMessage: 'Progress cannot be greater than 100');

test(description: 'an Event runs when an Achievement is earned', closure: function (): void {
    Event::fakeFor(callable: function (): void {
        $this->user->grantAchievement($this->achievement);
        Event::assertDispatched(event: AchievementAwarded::class);

        $this->user->grantAchievement($this->achievement, 100); // grant an achievement with 100% progress
        Event::assertDispatched(event: AchievementAwarded::class);
    });
});

test(description: 'an Event does not run when an Achievement is earned with progress less than 100', closure: function (): void {
    Event::fakeFor(callable: function (): void {
        $this->user->grantAchievement($this->achievement, 50);

        Event::assertNotDispatched(event: AchievementAwarded::class);
    });
});

test(description: 'a User can see Achievements with progress', closure: function (): void {
    $this->user->grantAchievement($this->achievement, 50);

    expect($this->user)->achievementsWithProgress->toHaveCount(1);
});

test(description: 'a User can see secret Achievements', closure: function (): void {
    $this->user->grantAchievement(Achievement::factory()->secret()->create());

    expect($this->user)->secretAchievements->toHaveCount(1)
        ->and($this->user)->achievements->toHaveCount(0);
});

test(description: 'a User can see all Achievements', closure: function (): void {
    $this->user->grantAchievement($this->achievement);
    $this->user->grantAchievement($this->achievement, 50);
    $this->user->grantAchievement(Achievement::factory()->secret()->create());

    expect($this->user)->allAchievements->toHaveCount(3);
});

it(description: 'can fetch Achievements that have a certain amount of progression', closure: function (): void {
    $this->user->grantAchievement($this->achievement, 50);
    $this->user->grantAchievement($this->achievement, 50);

    expect($this->user)->achievements->first()->pivot->withProgress(50)->toHaveCount(2);
});

it(description: 'can increment the progress of an Achievement', closure: function (): void {
    Event::fake();

    $this->user->grantAchievement($this->achievement, 50);

    $this->user->incrementAchievementProgress($this->achievement, 1);

    expect($this->user)->achievements->first()->pivot->progress->toBe(expected: 51);

    Event::assertDispatched(
        event: AchievementProgressionIncreased::class,
        callback: fn (AchievementProgressionIncreased $event): bool => $event->achievement->is($this->achievement)
            && $event->user->is($this->user)
            && $event->amount === 1
    );

    $this->assertDatabaseHas(table: 'achievement_user', data: [
        'user_id' => $this->user->id,
        'achievement_id' => $this->achievement->id,
        'progress' => 51,
    ]);
});
