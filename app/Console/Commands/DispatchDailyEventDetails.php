<?php

namespace App\Console\Commands;

use App\Services\Discord\DiscordDispatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;

#[Signature('discord:dispatch-event-details {--date= : Date in YYYY-MM-DD format}')]
#[Description('Send or update Discord event detail messages for the selected event date.')]
class DispatchDailyEventDetails extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DiscordDispatchService $discord): int
    {
        $date = $this->option('date');

        if (! is_string($date) || $date === '') {
            $date = Carbon::now('Asia/Jakarta')->toDateString();
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Date must use YYYY-MM-DD format.');

            return self::FAILURE;
        }

        try {
            $dispatches = $discord->syncDateDetails($date);
        } catch (RuntimeException $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        $this->info($dispatches->count().' Discord event detail message(s) synced.');

        return self::SUCCESS;
    }
}
