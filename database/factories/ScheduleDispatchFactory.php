<?php

namespace Database\Factories;

use App\Models\ScheduleDispatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleDispatch>
 */
class ScheduleDispatchFactory extends Factory
{
    protected $model = ScheduleDispatch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'month' => now()->startOfMonth()->toDateString(),
            'discord_message_id' => null,
            'discord_message_ids' => null,
            'webhook_hash' => null,
            'sent_at' => now(),
            'updated_sent_at' => null,
            'sent_by' => User::factory(),
        ];
    }
}
