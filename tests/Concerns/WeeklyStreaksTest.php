<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use LevelUp\Experience\Models\Activity;

uses()->group('streaks');

beforeEach(closure: function (): void {
    config()->set('level-up.streaks.period', 'week');
    config()->set('level-up.streaks.week_starts_on', CarbonInterface::MONDAY);
    config()->set('level-up.streaks.timezone', 'Asia/Manila');

    $this->activity = Activity::factory()->create();
});

test(description: 'activity twice in the same week keeps the streak at one', closure: function (): void {
    $this->travelTo(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    $this->travelTo(Carbon::parse('2026-11-08 20:00', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(1);
});

test(description: 'activity in the next week increases the streak', closure: function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 20:00', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    $this->travelTo(Carbon::parse('2026-11-09 07:00', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(2);
});

test(description: 'a week without activity breaks the streak', closure: function (): void {
    $this->travelTo(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    $this->travelTo(Carbon::parse('2026-11-16 09:00', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(1);
});

test(description: 'weeks are counted in the configured timezone', closure: function (): void {
    $this->travelTo(Carbon::parse('2026-11-08 23:30', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    $this->travelTo(Carbon::parse('2026-11-09 00:30', 'Asia/Manila'));
    $this->user->recordStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(2);
});

test(description: 'daily streaks are unchanged by default', closure: function (): void {
    config()->set('level-up.streaks.period', 'day');

    $this->travelTo(Carbon::parse('2026-11-02 09:00'));
    $this->user->recordStreak($this->activity);

    $this->travelTo(Carbon::parse('2026-11-04 09:00'));
    $this->user->recordStreak($this->activity);

    expect($this->user->getCurrentStreakCount($this->activity))->toBe(1);
});
