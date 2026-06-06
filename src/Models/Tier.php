<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;
use LevelUp\Experience\Exceptions\TierExistsException;

/**
 * @property int|string $id
 * @property string $name
 * @property int $experience
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Tier extends Model
{
    use HasConfigurableIds, HasFactory, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'experience' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * @param  array{name: string, experience: int, metadata?: array}  ...$tiers
     *
     * @throws TierExistsException
     */
    public static function add(array ...$tiers): array
    {
        $names = array_column($tiers, 'name');
        $existing = self::query()->whereIn('name', $names)->value('name');

        if ($existing) {
            throw TierExistsException::handle(tierName: $existing);
        }

        return DB::transaction(fn (): array => array_map(self::createTier(...), $tiers));
    }

    public static function forPoints(int $points): ?static
    {
        return static::query()
            ->where(column: 'experience', operator: '<=', value: $points)
            ->orderByDesc(column: 'experience')
            ->first();
    }

    protected function configuredTableKey(): string
    {
        return 'tiers';
    }

    private static function createTier(array $tier): static
    {
        try {
            return static::query()->create([
                'name' => $tier['name'],
                'experience' => $tier['experience'],
                'metadata' => $tier['metadata'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw TierExistsException::handle(tierName: $tier['name']);
        }
    }
}
