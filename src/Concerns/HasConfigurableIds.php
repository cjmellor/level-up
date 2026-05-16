<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueIds;
use Illuminate\Support\Str;

trait HasConfigurableIds
{
    use HasUniqueIds;

    public function initializeHasConfigurableIds(): void
    {
        $this->usesUniqueIds = $this->packageIdType() !== 'bigint';
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
        return match ($this->packageIdType()) {
            'ulid' => strtolower((string) Str::ulid()),
            'uuid' => (string) Str::uuid(),
            default => '',
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
