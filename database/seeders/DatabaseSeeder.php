<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\ManualFullDate;
use App\Models\ScheduleDispatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $name = (string) env('ADMIN_NAME', 'Queenlin Admin');
        $email = (string) env('ADMIN_EMAIL');
        $password = (string) env('ADMIN_PASSWORD');

        if (app()->isProduction() && (blank($email) || blank($password) || $password === 'password')) {
            throw new \RuntimeException('Set ADMIN_EMAIL and a strong ADMIN_PASSWORD before seeding production.');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        Event::query()
            ->where('created_by', '!=', $admin->id)
            ->update(['created_by' => $admin->id]);

        ManualFullDate::query()
            ->whereNotNull('marked_by')
            ->where('marked_by', '!=', $admin->id)
            ->update(['marked_by' => $admin->id]);

        ScheduleDispatch::query()
            ->whereNotNull('sent_by')
            ->where('sent_by', '!=', $admin->id)
            ->update(['sent_by' => $admin->id]);

        User::query()
            ->whereKeyNot($admin->id)
            ->delete();
    }
}
