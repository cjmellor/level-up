<?php

namespace LevelUp\Experience\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LevelUp\Experience\Models\Achievement;

class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    /**
     * @return array{name: string, description: string, image: string}
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name,
            'description' => fake()->sentence,
            'image' => fake()->imageUrl,
        ];
    }

    public function secret(): self
    {
        return $this->state([
            'is_secret' => true,
        ]);
    }
}
