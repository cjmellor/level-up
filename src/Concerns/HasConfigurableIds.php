<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueIds;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait HasConfigurableIds
{
    use HasUniqueIds;

    public function initializeHasConfigurableIds(): void
    {
        $this->usesUniqueIds = in_array($this->packageIdType(), ['uuid', 'ulid'], true);
    }

    public function getKeyType(): string
    {
        return $this->packageIdType() === 'bigint' ? 'int' : 'string';
    }

    public function getIncrementing(): bool
    {
        return $this->packageIdType() === 'bigint';
    }

    public function newUniqueId(): string
    {
        $type = $this->packageIdType();

        return match ($type) {
            'ulid' => strtolower((string) Str::ulid()),
            'uuid' => (string) Str::uuid(),
            default => throw new InvalidArgumentException(
                "Unknown level-up.entities.id_type [{$type}]. Expected 'bigint', 'uuid', or 'ulid'."
            ),
        };
    }

    public function uniqueIds(): array
    {
        return $this->packageIdType() === 'bigint'
            ? []
            : [$this->getKeyName()];
    }

    protected function packageIdType(): string
    {
        return config('level-up.entities.id_type', 'bigint');
    }
}
