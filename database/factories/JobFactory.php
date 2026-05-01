<?php

namespace Database\Factories;

use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'budget_min' => fake()->numberBetween(1000, 5000),
            'budget_max' => fake()->numberBetween(5001, 10000),
            'workers_needed' => 1,
            'location_district' => fake()->city(),
            'location_city' => fake()->city(),
            'status' => 'open',
            'start_at' => now()->addDay(),
            'deadline_at' => now()->addDays(7),
        ];
    }
}
