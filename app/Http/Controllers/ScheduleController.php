<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ManualFullDate;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleController extends Controller
{
    public function __invoke(Request $request): View
    {
        $month = $this->monthFromRequest($request);
        $selectedDate = $this->dateFromRequest($request, $month);

        $events = Event::query()
            ->published()
            ->forMonth($month)
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        $durationsByDate = $events
            ->groupBy(fn (Event $event) => $event->event_date->toDateString())
            ->map(fn ($events) => $events->sum(fn (Event $event) => $event->durationMinutes()));
        $eventCountsByDate = $events
            ->groupBy(fn (Event $event) => $event->event_date->toDateString())
            ->map(fn ($events) => $events->count());

        $selectedEvents = $events
            ->filter(fn (Event $event) => $event->event_date->isSameDay($selectedDate))
            ->values();

        $selectedEvent = $selectedEvents->firstWhere('id', (int) $request->query('event')) ?? $selectedEvents->first();
        $manualDateStatuses = ManualFullDate::statusesBetween(
            $month->copy()->startOfMonth()->startOfWeek(),
            $month->copy()->endOfMonth()->endOfWeek(),
        );
        $calendarDays = $this->calendarDays($month, $durationsByDate->all(), $eventCountsByDate->all(), $manualDateStatuses, $selectedDate);
        $selectedMinutes = (int) ($durationsByDate[$selectedDate->toDateString()] ?? 0);
        $selectedManualStatus = $manualDateStatuses[$selectedDate->toDateString()] ?? null;

        return view('schedule.index', [
            'calendarDays' => $calendarDays,
            'month' => $month,
            'monthOptions' => $this->monthOptions(),
            'selectedDate' => $selectedDate,
            'selectedDensity' => Event::densityForMinutes($selectedMinutes, $selectedManualStatus),
            'selectedEvent' => $selectedEvent,
            'selectedEvents' => $selectedEvents,
            'selectedManualStatus' => $selectedManualStatus,
            'selectedMinutes' => $selectedMinutes,
        ]);
    }

    private function monthFromRequest(Request $request): CarbonInterface
    {
        $month = $request->query('month');

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function dateFromRequest(Request $request, CarbonInterface $month): CarbonInterface
    {
        $date = $request->query('date');

        if (is_string($date)) {
            try {
                $parsed = Carbon::parse($date)->startOfDay();

                if ($parsed->isSameMonth($month)) {
                    return $parsed;
                }
            } catch (\Throwable) {
                //
            }
        }

        return now()->isSameMonth($month) ? now()->startOfDay() : $month->copy()->startOfMonth();
    }

    /**
     * @param  array<string, int>  $durationsByDate
     * @param  array<string, int>  $eventCountsByDate
     * @param  array<string, string>  $manualDateStatuses
     * @return array<int, array<string, mixed>>
     */
    private function calendarDays(CarbonInterface $month, array $durationsByDate, array $eventCountsByDate, array $manualDateStatuses, CarbonInterface $selectedDate): array
    {
        $days = [];
        $start = $month->copy()->startOfMonth()->startOfWeek();
        $end = $month->copy()->endOfMonth()->endOfWeek();

        for ($date = $start->copy(); $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $minutes = (int) ($durationsByDate[$key] ?? 0);
            $manualStatus = $manualDateStatuses[$key] ?? null;

            $days[] = [
                'date' => $date->copy(),
                'density' => Event::densityForMinutes($minutes, $manualStatus),
                'eventCount' => (int) ($eventCountsByDate[$key] ?? 0),
                'manualStatus' => $manualStatus,
                'isCurrentMonth' => $date->isSameMonth($month),
                'isSelected' => $date->isSameDay($selectedDate),
                'minutes' => $minutes,
            ];
        }

        return $days;
    }

    /**
     * @return array<int, Carbon>
     */
    private function monthOptions(): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(2);

        return collect(range(0, 14))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset))
            ->all();
    }
}
