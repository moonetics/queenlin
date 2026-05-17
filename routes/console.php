<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('discord:dispatch-monthly-schedules')
    ->monthlyOn(1, '00:00')
    ->timezone('Asia/Jakarta');

Schedule::command('discord:dispatch-event-details')
    ->dailyAt('00:00')
    ->timezone('Asia/Jakarta');
