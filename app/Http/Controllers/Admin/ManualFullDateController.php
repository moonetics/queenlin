<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ManualFullDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ManualFullDateController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            'status' => ['nullable', 'in:'.ManualFullDate::STATUS_FULL.','.ManualFullDate::STATUS_OFF],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $eventDate = Carbon::parse($validated['event_date'])->toDateString();
        $status = $validated['status'] ?? ManualFullDate::STATUS_FULL;
        $month = $validated['month'] ?? Carbon::parse($eventDate)->format('Y-m');
        $manualFullDate = ManualFullDate::query()
            ->whereDate('event_date', $eventDate)
            ->first();

        if ($status === ManualFullDate::STATUS_OFF && Event::query()->forDate($eventDate)->exists()) {
            return $this->redirectToAdmin(
                $month,
                $eventDate,
                'Tanggal ini masih punya event, jadi belum bisa ditandai libur.',
            );
        }

        if ($manualFullDate) {
            if ($manualFullDate->status === $status) {
                $manualFullDate->delete();

                return $this->redirectToAdmin($month, $eventDate, 'Tanggal berhasil dibuka lagi.');
            }

            $manualFullDate->update([
                'status' => $status,
                'marked_by' => $request->user()->id,
                'marked_at' => now(),
            ]);

            return $this->redirectToAdmin(
                $month,
                $eventDate,
                $status === ManualFullDate::STATUS_OFF ? 'Tanggal berhasil ditandai libur.' : 'Tanggal berhasil ditandai penuh.',
            );
        }

        ManualFullDate::create([
            'event_date' => $eventDate,
            'status' => $status,
            'marked_by' => $request->user()->id,
            'marked_at' => now(),
        ]);

        return $this->redirectToAdmin(
            $month,
            $eventDate,
            $status === ManualFullDate::STATUS_OFF ? 'Tanggal berhasil ditandai libur.' : 'Tanggal berhasil ditandai penuh.',
        );
    }

    private function redirectToAdmin(string $month, string $eventDate, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.events.index', ['month' => $month, 'date' => $eventDate])
            ->with('status', $message);
    }
}
