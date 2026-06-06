<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Models\Achievement;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\ExperienceAudit;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Models\Streak;
use LevelUp\Experience\Tests\Fixtures\User;

uses()->group('models');

test(description: 'models expose their user relations', closure: function (): void {
    expect((new Achievement)->users())->toBeInstanceOf(BelongsToMany::class)
        ->and((new Experience)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new Experience)->tier())->toBeInstanceOf(BelongsTo::class)
        ->and((new ExperienceAudit)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new Level)->users())->toBeInstanceOf(HasMany::class)
        ->and((new Streak)->user())->toBeInstanceOf(BelongsTo::class);
});

test(description: 'the experience user relation resolves the configured user model', closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect(Experience::query()->first()->user)->toBeInstanceOf(User::class);
});
