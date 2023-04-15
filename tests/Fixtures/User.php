<?php

namespace LevelUp\Experience\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\GiveExperience;

class User extends Model
{
    use GiveExperience;

    protected $guarded = [];
}
