<?php

declare(strict_types=1);

namespace LevelUp\Experience\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use InvalidArgumentException;

class UserForeignKey
{
    public static function on(Blueprint $table, ?string $column = null): ForeignIdColumnDefinition
    {
        $column ??= config('level-up.user.foreign_key', 'user_id');
        $type = config('level-up.user.foreign_key_type', 'bigint');

        return match ($type) {
            'bigint' => $table->foreignId($column),
            'uuid' => $table->foreignUuid($column),
            'ulid' => $table->foreignUlid($column),
            default => throw new InvalidArgumentException(
                "Unknown level-up.user.foreign_key_type [{$type}]. Expected 'bigint', 'uuid', or 'ulid'."
            ),
        };
    }
}
