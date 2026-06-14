<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Enums\DivisionDirection;
use LevelUp\Experience\Models\Division;

class UserDivisionChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly string $board,
        public readonly Division $previousDivision,
        public readonly Division $newDivision,
        public readonly DivisionDirection $direction,
    ) {}
}
