<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Exceptions\LevelExistsException;
use Throwable;

class Level extends Model
{
    protected $guarded = [];

    /**
     * @throws \LevelUp\Experience\Exceptions\LevelExistsException
     */
    public static function add(int $level, int $pointsToNextLevel): self
    {
        try {
            return self::create([
                'level' => $level,
                'next_level_experience' => $pointsToNextLevel,
            ]);
        } catch (Throwable $throwable) {
            throw LevelExistsException::handle(levelNumber: $level, exception: $throwable);
        }
    }
}
