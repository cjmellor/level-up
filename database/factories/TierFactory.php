<?php

declare(strict_types=1);

namespace LevelUp\Experience\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LevelUp\Experience\Models\Tier;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\LevelUp\Experience\Models\Tier>
 */
class TierFactory extends Factory
{
    protected $model = Tier::class;

    /**
     * @return array{name: string, experience: int}
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'experience' => fake()->unique()->numberBetween(100, 10000),
        ];
    }
}
