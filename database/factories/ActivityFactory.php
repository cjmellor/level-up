<?php

namespace LevelUp\Experience\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LevelUp\Experience\Models\Activity;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word,
            'description' => fake()->sentence,
        ];
    }
}
