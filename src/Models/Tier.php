<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Exceptions\TierExistsException;

class Tier extends Model
{
    use HasFactory;

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
        $existing = self::query()->whereIn('name', $names)->pluck('name')->first();

        if ($existing) {
            throw TierExistsException::handle(tierName: $existing);
        }

        return DB::transaction(fn (): array => array_map(self::createTier(...), $tiers));
    }

    public static function forPoints(int $points): ?static
    {
        return self::query()
            ->where(column: 'experience', operator: '<=', value: $points)
            ->orderByDesc(column: 'experience')
            ->first();
    }

    private static function createTier(array $tier): static
    {
        try {
            return self::query()->create([
                'name' => $tier['name'],
                'experience' => $tier['experience'],
                'metadata' => $tier['metadata'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw TierExistsException::handle(tierName: $tier['name']);
        }
    }
}
