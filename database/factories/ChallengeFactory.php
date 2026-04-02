<?php

declare(strict_types=1);

namespace LevelUp\Experience\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use LevelUp\Experience\Models\Challenge;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\LevelUp\Experience\Models\Challenge>
 */
class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    /**
     * @return array{name: string, description: string, conditions: array, rewards: array, auto_enroll: bool, is_repeatable: bool}
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(nbWords: 3),
            'description' => fake()->sentence(),
            'conditions' => [
                ['type' => 'points_earned', 'amount' => 100],
            ],
            'rewards' => [
                ['type' => 'points', 'amount' => 50],
            ],
            'auto_enroll' => false,
            'is_repeatable' => false,
        ];
    }

    public function autoEnroll(): static
    {
        return $this->state([
            'auto_enroll' => true,
        ]);
    }

    public function repeatable(): static
    {
        return $this->state([
            'is_repeatable' => true,
        ]);
    }

    public function startsAt(DateTimeInterface $date): static
    {
        return $this->state([
            'starts_at' => $date,
        ]);
    }

    public function expiresAt(DateTimeInterface $date): static
    {
        return $this->state([
            'expires_at' => $date,
        ]);
    }
}
