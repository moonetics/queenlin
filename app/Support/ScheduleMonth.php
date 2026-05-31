<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class ScheduleMonth
{
    public static function parse(string $month): Carbon
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException('Invalid schedule month.');
        }

        $date = Carbon::createFromFormat('!Y-m-d', $month.'-01');

        if (! $date instanceof Carbon) {
            throw new InvalidArgumentException('Invalid schedule month.');
        }

        return $date->startOfDay();
    }
}
