<?php

declare(strict_types=1);

namespace LevelUp\Experience\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LevelUp\Experience\Support\PeriodRange;

enum Period: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function range(): PeriodRange
    {
        $now = CarbonImmutable::now(timezone: $this->timezone());

        $start = match ($this) {
            self::Day => $now->startOfDay(),
            self::Week => $now->startOfWeek(weekStartsAt: config()->integer(key: 'level-up.leaderboard.week_starts_on', default: CarbonInterface::MONDAY)),
            self::Month => $now->startOfMonth(),
        };

        $end = match ($this) {
            self::Day => $start->addDay(),
            self::Week => $start->addWeek(),
            self::Month => $start->addMonth(),
        };

        $appTimezone = config()->string(key: 'app.timezone');

        return new PeriodRange(
            start: $start->setTimezone(timeZone: $appTimezone),
            end: $end->setTimezone(timeZone: $appTimezone),
        );
    }

    private function timezone(): string
    {
        $timezone = config(key: 'level-up.leaderboard.timezone');

        return is_string($timezone) ? $timezone : config()->string(key: 'app.timezone');
    }
}
