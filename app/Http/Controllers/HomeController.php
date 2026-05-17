<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $upcomingEvents = Event::query()
            ->published()
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(3)
            ->get();

        return view('home', [
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
