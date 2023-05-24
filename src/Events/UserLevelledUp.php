<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class UserLevelledUp
{
    use Dispatchable;

    public function __construct(
        public Model $user,
        public int $level
    ) {
    }
}
