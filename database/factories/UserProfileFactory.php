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
            'bio' => $this->faker->optional()->paragraph(),
            'location_district' => $this->faker->optional()->city(),
            'location_city' => $this->faker->optional()->city(),
            'avatar_url' => $this->faker->optional()->imageUrl(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'avg_rating' => 0,
            'total_jobs_posted' => 0,
            'total_jobs_completed' => 0,
        ];
    }
}
