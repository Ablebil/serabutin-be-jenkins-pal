<?php

namespace Database\Factories;

use App\Models\Bid;
use Illuminate\Database\Eloquent\Factories\Factory;

class BidFactory extends Factory
{
    protected $model = Bid::class;

    public function definition(): array
    {
        return [
            'proposed_price' => $this->faker->numberBetween(1000, 10000),
            'status' => 'pending',
        ];
    }
}
