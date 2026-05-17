<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DiscordSchedulePayloadBuilder
{
    /**
     * @return array{month: string, title: string, event_count: int, events: array<int, array<string, string|null>>}
     */
    public function forMonth(string $month): array
    {
        $date = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $events = $this->eventsForMonth($month);

        return [
            'month' => $date->toDateString(),
            'title' => 'Jadwal Queenlin - '.$date->translatedFormat('F Y'),
            'event_count' => $events->count(),
            'events' => $events->map(fn (Event $event) => [
                'date' => $event->event_date->format('Y-m-d'),
                'time' => $event->start_time->format('H:i').'-'.$event->end_time->format('H:i').' WIB',
                'title' => $event->title,
                'map_name' => $event->map_name,
                'theme' => $event->theme,
                'prizepool' => $event->formattedPrizepool(),
                'progress_status' => $event->progress_status,
                'progress_label' => $event->progressLabel(),
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    public function eventsForMonth(string $month): Collection
    {
        $date = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return Event::query()
            ->published()
            ->forMonth($date)
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();
    }
}
