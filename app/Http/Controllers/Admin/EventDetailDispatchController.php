<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Discord\DiscordDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EventDetailDispatchController extends Controller
{
    public function sendEvent(Request $request, Event $event, DiscordDispatchService $discord): RedirectResponse
    {
        try {
            $discord->sendEventDetail($event, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Detail event berhasil dikirim ke Discord.');
    }

    public function updateEvent(Request $request, Event $event, DiscordDispatchService $discord): RedirectResponse
    {
        try {
            $discord->updateEventDetail($event, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Detail event berhasil di-update ke Discord.');
    }

    public function deleteEvent(Event $event, DiscordDispatchService $discord): RedirectResponse
    {
        try {
            $discord->deleteEventDetail($event);
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Detail event berhasil dihapus dari Discord.');
    }

    public function sendDate(Request $request, DiscordDispatchService $discord): RedirectResponse
    {
        $date = $this->validatedDate($request);

        try {
            $count = $discord->sendDateDetails($date, $request->user())->count();
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', $count.' detail event berhasil dikirim ke Discord.');
    }

    public function updateDate(Request $request, DiscordDispatchService $discord): RedirectResponse
    {
        $date = $this->validatedDate($request);

        try {
            $count = $discord->updateDateDetails($date, $request->user())->count();
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', $count.' detail event berhasil di-update ke Discord.');
    }

    public function deleteDate(Request $request, DiscordDispatchService $discord): RedirectResponse
    {
        $date = $this->validatedDate($request);

        try {
            $count = $discord->deleteDateDetails($date);
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', $count.' detail event berhasil dihapus dari Discord.');
    }

    private function validatedDate(Request $request): string
    {
        $data = $request->validate([
            'event_date' => ['required', 'date_format:Y-m-d'],
        ]);

        return $data['event_date'];
    }
}
