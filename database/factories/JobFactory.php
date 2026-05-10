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
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'budget_min' => $this->faker->numberBetween(1000, 5000),
            'budget_max' => $this->faker->numberBetween(5001, 10000),
            'workers_needed' => 1,
            'location_district' => $this->faker->city(),
            'location_city' => $this->faker->city(),
            'status' => 'open',
            'start_at' => now()->addDay(),
            'deadline_at' => now()->addDays(7),
        ];
    }
}
