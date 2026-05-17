<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscordSetting;
use App\Models\ScheduleDispatch;
use App\Services\Discord\DiscordDispatchService;
use App\Services\DiscordSchedulePayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class ScheduleDispatchController extends Controller
{
    public function send(Request $request, DiscordSchedulePayloadBuilder $payloadBuilder, DiscordDispatchService $discord): RedirectResponse
    {
        $month = $this->validatedMonth($request);
        $payload = $payloadBuilder->forMonth($month);

        if ($payload['event_count'] === 0) {
            return back()->with('status', 'Belum ada event untuk dikirim ke Discord.');
        }

        $dispatch = ScheduleDispatch::query()->whereDate('month', ScheduleDispatch::monthDate($month))->first();
        $settings = DiscordSetting::current();

        if ($dispatch?->isForWebhook($settings->schedule_webhook_url)) {
            return back()->with('status', 'Schedule bulan ini sudah pernah dikirim. Gunakan Update Discord.');
        }

        try {
            $discord->sendMonthlySchedule($month, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Schedule '.$this->monthLabel($month).' berhasil dikirim ke Discord.');
    }

    public function update(Request $request, DiscordSchedulePayloadBuilder $payloadBuilder, DiscordDispatchService $discord): RedirectResponse
    {
        $month = $this->validatedMonth($request);
        $payload = $payloadBuilder->forMonth($month);

        if ($payload['event_count'] === 0) {
            return back()->with('status', 'Belum ada event untuk update Discord.');
        }

        try {
            $discord->updateMonthlySchedule($month, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Schedule '.$this->monthLabel($month).' berhasil di-update ke Discord.');
    }

    public function destroy(Request $request, DiscordDispatchService $discord): RedirectResponse
    {
        $month = $this->validatedMonth($request);

        try {
            $discord->deleteMonthlySchedule($month);
        } catch (RuntimeException $exception) {
            return back()->with('status', $exception->getMessage());
        }

        return back()->with('status', 'Schedule '.$this->monthLabel($month).' berhasil dihapus dari Discord.');
    }

    private function validatedMonth(Request $request): string
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        return $data['month'];
    }

    private function monthLabel(string $month): string
    {
        return Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y');
    }
}
