<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use LevelUp\Experience\Exceptions\LevelExistsException;

class Level extends Model
{
    protected $guarded = [];

    /**
     * @param  array{level: int, next_level_experience: int|null}  ...$levels
     *
     * @throws LevelExistsException
     */
    public static function add(array ...$levels): array
    {
        $newLevels = [];

        foreach ($levels as $level) {
            try {
                $newLevels[] = self::query()->create([
                    'level' => $level['level'],
                    'next_level_experience' => $level['next_level_experience'],
                ]);
            } catch (UniqueConstraintViolationException) {
                throw LevelExistsException::handle(levelNumber: $level['level']);
            }
        }

        return $newLevels;
    }

    public function users(): HasMany
    {
        return $this->hasMany(related: config(key: 'level-up.user.model'));
    }
}
