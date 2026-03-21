<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use LevelUp\Experience\Tests\Fixtures\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\LevelUp\Experience\Tests\Fixtures\User>
 */
class UserFactory extends Factory
{
    /**
     * @var string
     */
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
