<?php

namespace App\Console\Commands;

use App\Services\Discord\DiscordDispatchService;
use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Carbon;
use RuntimeException;

#[Signature('discord:dispatch-monthly-schedules {--month= : Month in YYYY-MM format}')]
#[Description('Send or update monthly Discord schedule on day 1 at 00:00 Asia/Jakarta.')]
class DispatchMonthlyDiscordSchedules extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DiscordDispatchService $discord): int
    {
        $month = $this->option('month');
        $now = Carbon::now('Asia/Jakarta');

        if (! is_string($month) || $month === '') {
            if ($now->day !== 1) {
                $this->info('Skipped. Monthly schedule automation only runs on day 1.');

                return self::SUCCESS;
            }

            $month = $now->format('Y-m');
        }

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Month must use YYYY-MM format.');

            return self::FAILURE;
        }

        try {
            $dispatch = $discord->syncMonthlySchedule($month);
        } catch (RuntimeException $exception) {
            $this->warn($exception->getMessage());

            return self::SUCCESS;
        }

        $this->info($dispatch ? 'Monthly Discord schedule synced.' : 'Monthly Discord schedule automation is disabled.');

        return self::SUCCESS;
    }
}
