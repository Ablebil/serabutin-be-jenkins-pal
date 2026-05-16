<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $jobId = (string) Str::uuid();
        $bidId = (string) Str::uuid();

        return [
            'type' => 'bid_accepted',
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'actor_id' => null,
            'data' => [
                'job_id' => $jobId,
                'job_title' => fake()->sentence(3),
                'bid_id' => $bidId,
            ],
            'read_at' => null,
        ];
    }
}
