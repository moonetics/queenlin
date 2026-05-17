<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ManualFullDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'event_date',
    'status',
    'marked_by',
    'marked_at',
])]
class ManualFullDate extends Model
{
    /** @use HasFactory<ManualFullDateFactory> */
    use HasFactory;

    public const STATUS_FULL = 'full';

    public const STATUS_OFF = 'off';

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'marked_at' => 'datetime',
        ];
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public static function isFullDate(CarbonInterface|string $date): bool
    {
        return self::statusForDate($date) === self::STATUS_FULL;
    }

    public static function isClosedDate(CarbonInterface|string $date): bool
    {
        return self::statusForDate($date) !== null;
    }

    public static function statusForDate(CarbonInterface|string $date): ?string
    {
        return self::query()
            ->whereDate('event_date', Carbon::parse($date)->toDateString())
            ->value('status');
    }

    /**
     * @return array<int, string>
     */
    public static function dateStringsBetween(CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        return array_keys(self::statusesBetween($start, $end));
    }

    /**
     * @return array<string, string>
     */
    public static function statusesBetween(CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        return self::query()
            ->whereBetween('event_date', [
                Carbon::parse($start)->toDateString(),
                Carbon::parse($end)->toDateString(),
            ])
            ->get(['event_date', 'status'])
            ->mapWithKeys(fn (self $dateStatus) => [
                $dateStatus->event_date->toDateString() => $dateStatus->status,
            ])
            ->all();
    }
}
