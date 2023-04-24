<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Exceptions\LevelExistsException;
use Throwable;

class Level extends Model
{
    protected $guarded = [];

    /**
     * @throws \LevelUp\Experience\Exceptions\LevelExistsException
     */
    public static function add(...$levels): array
    {
        $newLevels = [];

        foreach ($levels as $level) {
            if (is_array($level)) {
                $levelNumber = $level['level'];
                $pointsToNextLevel = $level['next_level_experience'];
            } else {
                $levelNumber = $level;
                $pointsToNextLevel = $levels[1] ?? 0;
            }

            try {
                $newLevels[] = self::create([
                    'level' => $levelNumber,
                    'next_level_experience' => $pointsToNextLevel,
                ]);
            } catch (Throwable $throwable) {
                throw LevelExistsException::handle(levelNumber: $levelNumber);
            }

            if (!is_array($level)) {
                break;
            }
        }

        return $newLevels;
    }

    public static function getLastLevel(): int
    {
        return self::latest()->first()->level;
    }

    public function users(): HasMany
    {
        return $this->hasMany(related: config(key: 'level-up.user.model'));
    }
}
