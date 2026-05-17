<?php

namespace Database\Factories;

use App\Models\ManualFullDate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualFullDate>
 */
class ManualFullDateFactory extends Factory
{
    protected $model = ManualFullDate::class;

    public function definition(): array
    {
        return [
            'event_date' => $this->faker->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'status' => ManualFullDate::STATUS_FULL,
            'marked_by' => User::factory(),
            'marked_at' => now(),
        ];
    }
}
