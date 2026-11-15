<?php

namespace Database\Factories;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'device_id' => $this->faker->randomElement(['nodo-python-01', 'nodo-python-02']),
        'event_type' => 'merodeo_confirmado',
        'confidence' => $this->faker->randomFloat(2, 0.60, 0.99),
        'image_path' => 'alerts/dummy-image.jpg', 
        'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
    ];
}
}
