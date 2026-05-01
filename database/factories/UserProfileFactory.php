<?php

namespace Database\Factories;

use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        return [
            'bio' => fake()->optional()->paragraph(),
            'location_district' => fake()->optional()->city(),
            'location_city' => fake()->optional()->city(),
            'avatar_url' => fake()->optional()->imageUrl(),
            'phone' => fake()->optional()->phoneNumber(),
            'avg_rating' => 0,
            'total_jobs_posted' => 0,
            'total_jobs_completed' => 0,
        ];
    }
}
