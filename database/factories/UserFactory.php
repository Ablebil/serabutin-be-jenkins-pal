<?php

namespace Database\Factories;

use App\Models\User;
use Database\Factories\UserProfileFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'secret12345',
            'full_name' => fake()->name(),
            'role' => fake()->randomElement(['client', 'worker']),
            'is_verified' => true,
            'is_active' => true,
        ];
    }

    public function unverified(): self
    {
        return $this->state(fn(array $attributes) => ['is_verified' => false]);
    }

    public function inactive(): self
    {
        return $this->state(fn(array $attributes) => ['is_active' => false]);
    }

    public function client(): self
    {
        return $this->state(fn(array $attributes) => ['role' => 'client']);
    }

    public function worker(): self
    {
        return $this->state(fn(array $attributes) => ['role' => 'worker']);
    }

    public function withProfile(array $attributes = []): self
    {
        return $this->afterCreating(function (User $user) use ($attributes) {
            UserProfileFactory::new()->create(array_merge([
                'user_id' => $user->id,
            ], $attributes));
        });
    }
}
