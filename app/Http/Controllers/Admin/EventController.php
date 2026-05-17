<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\DiscordSetting;
use App\Models\Event;
use App\Models\ManualFullDate;
use App\Models\ScheduleDispatch;
use App\Services\DiscordSchedulePayloadBuilder;
use App\Services\Discord\DiscordDispatchService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function index(Request $request, DiscordSchedulePayloadBuilder $payloadBuilder, DiscordDispatchService $discordDispatch): View
    {
        $month = $this->monthFromRequest($request);
        $date = $this->dateFromRequest($request, $month);
        $query = Event::query()
            ->with(['creator', 'detailDispatch'])
            ->orderByDesc('event_date')
            ->orderBy('start_time');

        $query->whereYear('event_date', substr($month, 0, 4))
            ->whereMonth('event_date', substr($month, 5, 2));

        if ($date) {
            $query->whereDate('event_date', $date);
        }

        $schedulePayload = $payloadBuilder->forMonth($month);
        $discordSettings = DiscordSetting::current();
        $manualDateStatus = $date ? ManualFullDate::statusForDate($date) : null;
        $adminDateMonths = $this->dateFilterData($month);
        $selectedDateSlot = $date ? ($adminDateMonths[$month]['dates'][$date] ?? null) : null;
        $dateDetailDispatchState = $date ? $discordDispatch->dateState($date) : [
            'published_count' => 0,
            'sent_count' => 0,
            'unsent_count' => 0,
        ];

        return view('admin.events.index', [
            'events' => $query->paginate(10)->withQueryString(),
            'month' => $month,
            'date' => $date,
            'adminDateMonths' => $adminDateMonths,
            'adminMonthOptions' => $this->adminMonthOptions($month),
            'selectedDateSlot' => $selectedDateSlot,
            'discordSettings' => $discordSettings,
            'dateDetailDispatchState' => $dateDetailDispatchState,
            'manualDateStatus' => $manualDateStatus,
            'manualDateStatusLabel' => match ($manualDateStatus) {
                ManualFullDate::STATUS_FULL => 'Penuh manual',
                ManualFullDate::STATUS_OFF => 'Libur',
                default => 'Normal',
            },
            'selectedDateEventCount' => $date ? Event::query()->forDate($date)->count() : 0,
            'schedulePayload' => $schedulePayload,
            'scheduleDispatch' => ScheduleDispatch::query()
                ->whereDate('month', ScheduleDispatch::monthDate($month))
                ->first(),
        ]);
    }

    public function create(): View
    {
        $event = new Event([
            'start_time' => '15:00',
            'end_time' => '00:00',
            'status' => Event::STATUS_PUBLISHED,
            'progress_status' => Event::PROGRESS_SCHEDULED,
        ]);

        return view('admin.events.form', [
            'event' => $event,
            'action' => route('admin.events.store'),
            'method' => 'POST',
            ...$this->formSlotData($event),
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['progress_status'] = Event::PROGRESS_SCHEDULED;

        if ($request->hasFile('poster')) {
            $data['poster'] = $this->storePoster($request->file('poster'));
        }

        $event = Event::create($data);

        return $this->redirectToEventDate($event, 'Event berhasil ditambahkan.');
    }

    public function edit(Event $event): View
    {
        return view('admin.events.form', [
            'event' => $event,
            'action' => route('admin.events.update', $event),
            'method' => 'PUT',
            ...$this->formSlotData($event),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();
        $oldPoster = $event->poster;
        $newPoster = null;

        if ($request->hasFile('poster')) {
            $newPoster = $this->storePoster($request->file('poster'));
            $data['poster'] = $newPoster;
        }

        $event->update($data);

        if ($newPoster) {
            $this->deletePoster($oldPoster);
        }

        return $this->redirectToEventDate($event, 'Event berhasil diperbarui.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $eventDate = $event->event_date?->toDateString();

        $this->deletePoster($event);
        $event->delete();

        return $this->redirectToEventDate($eventDate, 'Event berhasil dihapus.');
    }

    public function updateProgress(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'progress_status' => ['required', Rule::in([
                Event::PROGRESS_SCHEDULED,
                Event::PROGRESS_DONE,
                Event::PROGRESS_CANCELED,
            ])],
        ]);

        $event->update(['progress_status' => $data['progress_status']]);

        return back()->with('status', 'Status event berhasil diperbarui.');
    }

    private function storePoster(UploadedFile $poster): string
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            throw ValidationException::withMessages([
                'poster' => 'Poster event gagal diproses karena server belum mendukung optimasi gambar WebP.',
            ]);
        }

        $source = null;
        $resized = null;
        $temporaryPath = null;

        try {
            $source = match ($poster->getMimeType()) {
                'image/jpeg' => @imagecreatefromjpeg($poster->getRealPath()),
                'image/png' => @imagecreatefrompng($poster->getRealPath()),
                'image/webp' => @imagecreatefromwebp($poster->getRealPath()),
                default => null,
            };

            if (! $source) {
                throw new \RuntimeException('Unsupported or unreadable poster image.');
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $maxWidth = 1200;
            $maxHeight = 1600;
            $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);

            if (! $resized) {
                throw new \RuntimeException('Failed to create resized poster canvas.');
            }

            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            if (! imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
                throw new \RuntimeException('Failed to resize poster image.');
            }

            $temporaryPath = tempnam(sys_get_temp_dir(), 'queenlin-poster-');

            if (! $temporaryPath || ! imagewebp($resized, $temporaryPath, 82)) {
                throw new \RuntimeException('Failed to encode poster as WebP.');
            }

            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new \RuntimeException('Failed to read optimized poster.');
            }

            $path = 'event-posters/'.Str::uuid().'.webp';

            if (! Storage::disk('public')->put($path, $contents)) {
                throw new \RuntimeException('Failed to store poster on public disk.');
            }

            return $path;
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'poster' => 'Poster event gagal diproses. Pastikan file JPG, PNG, atau WebP valid dan ukurannya maksimal '.Event::POSTER_MAX_MEGABYTES.' MB.',
            ]);
        } finally {
            if ($source) {
                imagedestroy($source);
            }

            if ($resized) {
                imagedestroy($resized);
            }

            if ($temporaryPath) {
                @unlink($temporaryPath);
            }
        }
    }

    private function deletePoster(Event|string|null $poster): void
    {
        $path = $poster instanceof Event ? $poster->poster : $poster;

        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function redirectToEventDate(Event|string|null $eventOrDate, string $message): RedirectResponse
    {
        $date = $eventOrDate instanceof Event
            ? $eventOrDate->event_date?->toDateString()
            : $eventOrDate;

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return redirect()->route('admin.events.index')->with('status', $message);
        }

        return redirect()
            ->route('admin.events.index', ['month' => substr($date, 0, 7), 'date' => $date])
            ->with('status', $message);
    }

    private function monthFromRequest(Request $request): string
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : now()->format('Y-m');
    }

    private function dateFromRequest(Request $request, string $month): string
    {
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $fallback = $monthDate->isSameMonth(now()) ? now()->toDateString() : $monthDate->toDateString();
        $date = $request->query('date');

        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $fallback;
        }

        try {
            $parsedDate = Carbon::parse($date);

            return $parsedDate->format('Y-m') === $month ? $parsedDate->toDateString() : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function adminMonthOptions(string $selectedMonth): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(2);
        $end = now()->copy()->startOfMonth()->addMonths(14);
        $selected = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();

        if ($selected->lt($start)) {
            $start = $selected->copy();
        }

        if ($selected->gt($end)) {
            $end = $selected->copy();
        }

        $eventCounts = Event::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->copy()->endOfMonth()->toDateString()])
            ->get(['event_date'])
            ->groupBy(fn (Event $event) => $event->event_date->format('Y-m'))
            ->map(fn (Collection $events) => $events->count());
        $months = [];

        for ($month = $start->copy(); $month->lte($end); $month = $month->addMonth()) {
            $value = $month->format('Y-m');

            $months[] = [
                'value' => $value,
                'label' => $month->translatedFormat('F Y'),
                'eventCount' => (int) ($eventCounts[$value] ?? 0),
            ];
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    private function formSlotData(Event $event): array
    {
        $exceptEventId = $event->exists ? $event->id : null;
        $startMonth = now()->copy()->startOfMonth()->subMonth();
        $endMonth = now()->copy()->startOfMonth()->addMonths(14)->endOfMonth();
        $manualDateStatuses = ManualFullDate::statusesBetween($startMonth, $endMonth);
        $eventsByDate = $this->eventsByDate($startMonth, $endMonth, $exceptEventId);
        $dates = [];

        for ($date = $startMonth->copy(); $date->lte($endMonth); $date = $date->addDay()) {
            $value = $date->toDateString();
            $dates[$value] = $this->dateSlot(
                $date,
                $manualDateStatuses[$value] ?? null,
                $eventsByDate->get($value, collect()),
            );
        }

        $selectedDate = $this->selectedDateForForm($event);

        if (! isset($dates[$selectedDate])) {
            if ($selectedDate !== '') {
                $date = Carbon::parse($selectedDate);
                $dates[$selectedDate] = $this->dateSlot(
                    $date,
                    ManualFullDate::statusForDate($selectedDate),
                    $this->eventsForDate($selectedDate, $exceptEventId),
                );
            }
        }

        $dateMonths = collect($dates)
            ->groupBy(fn (array $slot, string $date) => substr($date, 0, 7), preserveKeys: true)
            ->map(fn ($monthDates, string $month) => [
                'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y'),
                'dates' => $monthDates->all(),
            ])
            ->all();

        return [
            'dateMonths' => $dateMonths,
            'timeOptions' => Event::timeOptions(),
            'selectedDate' => $selectedDate,
            'selectedMonth' => $this->selectedMonthForForm($selectedDate),
            'canKeepInitialDate' => $event->exists,
        ];
    }

    private function selectedDateForForm(Event $event): string
    {
        $fallback = optional($event->event_date)->format('Y-m-d') ?: '';
        $candidate = old('event_date', $fallback);

        if (! is_string($candidate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            return $fallback;
        }

        try {
            return Carbon::parse($candidate)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function selectedMonthForForm(string $selectedDate): string
    {
        $fallback = $selectedDate !== '' ? substr($selectedDate, 0, 7) : now()->format('Y-m');
        $candidate = old('schedule_month', $fallback);

        return is_string($candidate) && preg_match('/^\d{4}-\d{2}$/', $candidate) ? $candidate : $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function dateFilterData(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $manualDateStatuses = ManualFullDate::statusesBetween($start, $end);
        $eventsByDate = $this->eventsByDate($start, $end, null);
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date = $date->addDay()) {
            $value = $date->toDateString();
            $dates[$value] = $this->dateSlot(
                $date,
                $manualDateStatuses[$value] ?? null,
                $eventsByDate->get($value, collect()),
            );
        }

        return [
            $month => [
                'label' => $start->translatedFormat('F Y'),
                'dates' => $dates,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dateSlot(CarbonInterface $date, ?string $manualStatus, Collection $events): array
    {
        $occupiedMinuteRanges = $this->occupiedMinuteRanges($events);
        $usedMinutes = $events->sum(fn (Event $event) => $event->durationMinutes());
        $remainingMinutes = max(0, Event::OPERATIONAL_DURATION_MINUTES - $usedMinutes);
        $isClosed = $manualStatus !== null || ! $this->hasAvailableSlot($occupiedMinuteRanges);
        $eventCount = $events->count();
        $statusLabel = match ($manualStatus) {
            ManualFullDate::STATUS_FULL => 'Penuh manual',
            ManualFullDate::STATUS_OFF => 'Libur',
            default => $isClosed ? 'Penuh' : 'Sisa '.$this->durationLabel($remainingMinutes),
        };

        return [
            'label' => $date->translatedFormat('d M Y'),
            'dayLabel' => $date->translatedFormat('d'),
            'disabled' => $isClosed,
            'manualFull' => $manualStatus === ManualFullDate::STATUS_FULL,
            'manualOff' => $manualStatus === ManualFullDate::STATUS_OFF,
            'manualStatus' => $manualStatus,
            'statusLabel' => $statusLabel,
            'eventCount' => $eventCount,
            'usedMinutes' => $usedMinutes,
            'remainingMinutes' => $remainingMinutes,
            'occupiedRanges' => $this->occupiedRanges($events),
            'occupied' => $this->occupiedHourStarts($events),
        ];
    }

    /**
     * @return Collection<string, Collection<int, Event>>
     */
    private function eventsByDate(CarbonInterface $start, CarbonInterface $end, ?int $exceptEventId): Collection
    {
        return Event::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->when($exceptEventId, fn ($query) => $query->whereKeyNot($exceptEventId))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get(['id', 'event_date', 'start_time', 'end_time'])
            ->groupBy(fn (Event $event) => $event->event_date->toDateString());
    }

    /**
     * @return Collection<int, Event>
     */
    private function eventsForDate(string $date, ?int $exceptEventId): Collection
    {
        return Event::query()
            ->forDate($date)
            ->when($exceptEventId, fn ($query) => $query->whereKeyNot($exceptEventId))
            ->orderBy('start_time')
            ->get(['id', 'event_date', 'start_time', 'end_time']);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{start: int, end: int}>
     */
    private function occupiedMinuteRanges(Collection $events): array
    {
        return $events
            ->map(fn (Event $event) => [
                'start' => Event::minutesFromTime($event->start_time),
                'end' => Event::normalizeEndMinutes($event->end_time),
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
     * @param  array<int, array{start: int, end: int}>  $ranges
     */
    private function hasAvailableSlot(array $ranges): bool
    {
        $cursor = Event::OPERATIONAL_START_MINUTES;

        foreach ($ranges as $range) {
            if ($range['start'] - $cursor >= Event::MIN_BOOKING_MINUTES) {
                return true;
            }

            $cursor = max($cursor, $range['end']);
        }

        return Event::OPERATIONAL_END_MINUTES - $cursor >= Event::MIN_BOOKING_MINUTES;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, array{start: string, end: string, label: string}>
     */
    private function occupiedRanges(Collection $events): array
    {
        return $events
            ->map(fn (Event $event) => [
                'start' => $event->start_time->format('H:i'),
                'end' => $event->end_time->format('H:i'),
                'label' => $event->start_time->format('H:i').'-'.$event->end_time->format('H:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, string>
     */
    private function occupiedHourStarts(Collection $events): array
    {
        $occupied = [];

        $events->each(function (Event $event) use (&$occupied): void {
            $start = Event::minutesFromTime($event->start_time);
            $end = Event::normalizeEndMinutes($event->end_time);

            for ($minute = $start; $minute < $end; $minute += 60) {
                $occupied[] = sprintf('%02d:00', intdiv($minute, 60));
            }
        });

        return array_values(array_unique($occupied));
    }

    private function durationLabel(int $minutes): string
    {
        return intdiv($minutes, 60).'j '.($minutes % 60).'m';
    }
}
