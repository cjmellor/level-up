<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserLevelledUp
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $user,
        public int $level
    ) {
        //
    }
}
