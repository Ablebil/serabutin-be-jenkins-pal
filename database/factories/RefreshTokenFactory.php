<?php

namespace Database\Factories;

use App\Models\RefreshToken;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    public function definition(): array
    {
        return [
            'token_hash' => fake()->unique()->sha256(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
        ];
    }
}
