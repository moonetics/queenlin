<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'poster' => null,
            'theme' => fake()->randomElement(['Battle Night', 'Royal Match', 'Community Cup']),
            'map_name' => fake()->randomElement(['Erangel', 'Miramar', 'Sanhok']),
            'prizepool' => fake()->optional()->randomElement(['Rp 500.000', 'Rp 1.000.000']),
            'event_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'description' => fake()->optional()->paragraph(),
            'status' => Event::STATUS_PUBLISHED,
            'progress_status' => Event::PROGRESS_SCHEDULED,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Event::STATUS_DRAFT,
        ]);
    }
}
