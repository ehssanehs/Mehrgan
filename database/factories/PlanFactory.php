<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Plan',
            'price' => $this->faker->numberBetween(50000, 500000),
            'features' => "Unlimited Traffic\nHigh Speed\n24/7 Support",
            'is_popular' => false,
            'is_active' => true,
            'server_type' => 'all',
            'volume_gb' => $this->faker->numberBetween(10, 1000),
            'duration_days' => $this->faker->randomElement([30, 90, 180, 365]),
        ];
    }
}
