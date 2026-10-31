<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;

it('does not register the email verification listener a second time', function (): void {
    $verification = collect(Event::getRawListeners()[Registered::class] ?? [])
        ->filter(fn (mixed $listener): bool => $listener === SendEmailVerificationNotification::class
            || (is_string($listener) && str_starts_with($listener, SendEmailVerificationNotification::class)));

    expect($verification)->toHaveCount(0);
});
