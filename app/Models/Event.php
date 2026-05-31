<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'title',
    'poster',
    'theme',
    'map_name',
    'prizepool',
    'event_date',
    'start_time',
    'end_time',
    'description',
    'status',
    'progress_status',
    'created_by',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const PROGRESS_SCHEDULED = 'scheduled';

    public const PROGRESS_DONE = 'done';

    public const PROGRESS_CANCELED = 'canceled';

    public const DENSITY_AVAILABLE = 'available';

    public const DENSITY_BUSY = 'busy';

    public const DENSITY_FULL = 'full';

    public const DENSITY_OFF = 'off';

    public const OPERATIONAL_START_MINUTES = 15 * 60;

    public const OPERATIONAL_END_MINUTES = 24 * 60;

    public const OPERATIONAL_DURATION_MINUTES = self::OPERATIONAL_END_MINUTES - self::OPERATIONAL_START_MINUTES;

    public const MIN_BOOKING_MINUTES = 5;

    public const TIME_OPTION_INTERVAL_MINUTES = 5;

    public const POSTER_MAX_KILOBYTES = 2048;

    public const POSTER_MAX_MEGABYTES = 2;

    protected $appends = ['poster_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detailDispatch(): HasOne
    {
        return $this->hasOne(EventDetailDispatch::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function progressLabel(): string
    {
        return match ($this->progress_status) {
            self::PROGRESS_DONE => 'Selesai',
            self::PROGRESS_CANCELED => 'Cancel',
            default => 'Terjadwal',
        };
    }

    public function scopeForMonth(Builder $query, CarbonInterface $month): Builder
    {
        return $query
            ->whereDate('event_date', '>=', $month->copy()->startOfMonth()->toDateString())
            ->whereDate('event_date', '<=', $month->copy()->endOfMonth()->toDateString());
    }

    public function scopeForDate(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereDate('event_date', Carbon::parse($date)->toDateString());
    }

    public function posterUrl(): Attribute
    {
        return Attribute::get(fn () => $this->poster ? Storage::disk('public')->url($this->poster) : null);
    }

    public function durationMinutes(): int
    {
        return self::durationBetween($this->start_time, $this->end_time);
    }

    public static function durationBetween(mixed $startTime, mixed $endTime): int
    {
        $start = self::minutesFromTime($startTime);
        $end = self::minutesFromTime($endTime);

        if ($end === 0) {
            $end = self::OPERATIONAL_END_MINUTES;
        }

        return max(0, $end - $start);
    }

    public static function timeRangeOverlaps(mixed $firstStart, mixed $firstEnd, mixed $secondStart, mixed $secondEnd): bool
    {
        $firstStartMinutes = self::minutesFromTime($firstStart);
        $firstEndMinutes = self::normalizeEndMinutes($firstEnd);
        $secondStartMinutes = self::minutesFromTime($secondStart);
        $secondEndMinutes = self::normalizeEndMinutes($secondEnd);

        return $firstStartMinutes < $secondEndMinutes && $secondStartMinutes < $firstEndMinutes;
    }

    public static function usedMinutesForDate(CarbonInterface|string $date, ?int $exceptEventId = null): int
    {
        return self::query()
            ->forDate($date)
            ->when($exceptEventId, fn (Builder $query) => $query->whereKeyNot($exceptEventId))
            ->get()
            ->sum(fn (self $event) => $event->durationMinutes());
    }

    public static function remainingMinutesForDate(CarbonInterface|string $date, ?int $exceptEventId = null): int
    {
        return max(0, self::OPERATIONAL_DURATION_MINUTES - self::usedMinutesForDate($date, $exceptEventId));
    }

    public static function isDateClosed(CarbonInterface|string $date, ?int $exceptEventId = null, bool $includeManualFull = true): bool
    {
        if ($includeManualFull && ManualFullDate::isClosedDate($date)) {
            return true;
        }

        return ! self::hasAvailableSlotForDate($date, $exceptEventId);
    }

    public static function hasAvailableSlotForDate(CarbonInterface|string $date, ?int $exceptEventId = null): bool
    {
        $cursor = self::OPERATIONAL_START_MINUTES;

        foreach (self::occupiedMinuteRanges($date, $exceptEventId) as $range) {
            if ($range['start'] - $cursor >= self::MIN_BOOKING_MINUTES) {
                return true;
            }

            $cursor = max($cursor, $range['end']);
        }

        return self::OPERATIONAL_END_MINUTES - $cursor >= self::MIN_BOOKING_MINUTES;
    }

    public static function densityForMinutes(int $minutes, bool|string|null $manualStatus = null): array
    {
        if ($manualStatus === true) {
            $manualStatus = ManualFullDate::STATUS_FULL;
        }

        if ($manualStatus === ManualFullDate::STATUS_OFF) {
            return [
                'key' => self::DENSITY_OFF,
                'label' => 'Libur',
                'shortLabel' => 'Libur',
                'classes' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-400/50 dark:bg-sky-500/15 dark:text-sky-100',
                'dot' => 'bg-sky-400',
            ];
        }

        $remainingMinutes = max(0, self::OPERATIONAL_DURATION_MINUTES - $minutes);

        return match (true) {
            $manualStatus === ManualFullDate::STATUS_FULL || $minutes >= 480 || $remainingMinutes <= 60 => [
                'key' => self::DENSITY_FULL,
                'label' => 'Penuh',
                'shortLabel' => 'Penuh',
                'classes' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-400/50 dark:bg-red-500/15 dark:text-red-100',
                'dot' => 'bg-red-500',
            ],
            $minutes > 240 => [
                'key' => self::DENSITY_BUSY,
                'label' => 'Padat',
                'shortLabel' => 'Padat',
                'classes' => 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-300/60 dark:bg-orange-500/15 dark:text-orange-100',
                'dot' => 'bg-orange-400',
            ],
            default => [
                'key' => self::DENSITY_AVAILABLE,
                'label' => 'Tersedia',
                'shortLabel' => 'Ada',
                'classes' => 'border-lime-200 bg-lime-50 text-lime-800 dark:border-lime-300/70 dark:bg-emerald-500/15 dark:text-lime-100',
                'dot' => 'bg-lime-400',
            ],
        };
    }

    public function formattedPrizepool(): string
    {
        if (! filled($this->prizepool)) {
            return '-';
        }

        $amount = (int) preg_replace('/\D+/', '', (string) $this->prizepool);

        if ($amount <= 0) {
            return '-';
        }

        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    public static function normalizePrizepool(mixed $value): ?string
    {
        $amount = (int) preg_replace('/\D+/', '', (string) $value);

        return $amount > 0 ? (string) $amount : null;
    }

    public static function timeOptions(): array
    {
        return collect(range(self::OPERATIONAL_START_MINUTES, self::OPERATIONAL_END_MINUTES, self::TIME_OPTION_INTERVAL_MINUTES))
            ->map(function (int $minutes) {
                $hour = intdiv($minutes, 60);
                $minute = $minutes % 60;

                return [
                    'value' => sprintf('%02d:%02d', $hour === 24 ? 0 : $hour, $minute),
                    'label' => sprintf('%02d:%02d', $hour === 24 ? 0 : $hour, $minute),
                    'minutes' => $minutes,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{start: int, end: int}>
     */
    public static function occupiedMinuteRanges(CarbonInterface|string $date, ?int $exceptEventId = null): array
    {
        return self::query()
            ->forDate($date)
            ->when($exceptEventId, fn (Builder $query) => $query->whereKeyNot($exceptEventId))
            ->orderBy('start_time')
            ->get()
            ->map(fn (self $event) => [
                'start' => self::minutesFromTime($event->start_time),
                'end' => self::normalizeEndMinutes($event->end_time),
            ])
            ->sortBy('start')
            ->values()
            ->reduce(function (array $ranges, array $range): array {
                if ($ranges === [] || $range['start'] > $ranges[array_key_last($ranges)]['end']) {
                    $ranges[] = $range;

                    return $ranges;
                }

                $lastKey = array_key_last($ranges);
                $ranges[$lastKey]['end'] = max($ranges[$lastKey]['end'], $range['end']);

                return $ranges;
            }, []);
    }

    /**
     * @return array<int, array{start: string, end: string, label: string}>
     */
    public static function occupiedRanges(CarbonInterface|string $date, ?int $exceptEventId = null): array
    {
        return self::query()
            ->forDate($date)
            ->when($exceptEventId, fn (Builder $query) => $query->whereKeyNot($exceptEventId))
            ->orderBy('start_time')
            ->get()
            ->map(fn (self $event) => [
                'start' => $event->start_time->format('H:i'),
                'end' => $event->end_time->format('H:i'),
                'label' => $event->start_time->format('H:i').'-'.$event->end_time->format('H:i'),
            ])
            ->values()
            ->all();
    }

    public static function occupiedHourStarts(CarbonInterface|string $date, ?int $exceptEventId = null): array
    {
        $occupied = [];

        self::query()
            ->forDate($date)
            ->when($exceptEventId, fn (Builder $query) => $query->whereKeyNot($exceptEventId))
            ->get()
            ->each(function (self $event) use (&$occupied): void {
                $start = self::minutesFromTime($event->start_time);
                $end = self::normalizeEndMinutes($event->end_time);

                for ($minute = $start; $minute < $end; $minute += 60) {
                    $occupied[] = sprintf('%02d:00', intdiv($minute, 60));
                }
            });

        return array_values(array_unique($occupied));
    }

    public static function minutesFromTime(mixed $time): int
    {
        $value = $time instanceof CarbonInterface ? $time->format('H:i') : (string) $time;
        [$hours, $minutes] = array_map('intval', explode(':', substr($value, 0, 5)));

        return ($hours * 60) + $minutes;
    }

    public static function normalizeEndMinutes(mixed $time): int
    {
        $minutes = self::minutesFromTime($time);

        return $minutes === 0 ? self::OPERATIONAL_END_MINUTES : $minutes;
    }
}
