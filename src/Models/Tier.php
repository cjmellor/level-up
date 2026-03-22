<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

        return array_map(fn (array $tier) => self::query()->create([
            'name' => $tier['name'],
            'experience' => $tier['experience'],
            'metadata' => $tier['metadata'] ?? null,
        ]), $tiers);
    }
}
