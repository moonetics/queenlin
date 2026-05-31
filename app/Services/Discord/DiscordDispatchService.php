<?php

namespace App\Services\Discord;

use App\Models\DiscordSetting;
use App\Models\Event;
use App\Models\EventDetailDispatch;
use App\Models\ScheduleDispatch;
use App\Models\User;
use App\Support\ScheduleMonth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class DiscordDispatchService
{
    public function __construct(
        private readonly DiscordWebhookClient $client,
        private readonly DiscordMessageBuilder $messageBuilder,
    ) {}

    public function sendMonthlySchedule(string $month, ?User $user = null): ScheduleDispatch
    {
        $settings = $this->settingsWithScheduleWebhook();
        $messageIds = $this->client->sendMany($settings->schedule_webhook_url, $this->messageBuilder->monthlySchedule($month));
        $webhookHash = DiscordSetting::webhookHash($settings->schedule_webhook_url);

        $dispatch = ScheduleDispatch::query()
            ->whereDate('month', ScheduleDispatch::monthDate($month))
            ->first() ?: new ScheduleDispatch(['month' => ScheduleDispatch::monthDate($month)]);

        $dispatch->fill([
            'discord_message_id' => $messageIds[0] ?? null,
            'discord_message_ids' => $messageIds,
            'webhook_hash' => $webhookHash,
            'sent_at' => now(),
            'updated_sent_at' => null,
            'sent_by' => $user?->id,
        ])->save();

        return $dispatch;
    }

    public function updateMonthlySchedule(string $month, ?User $user = null): ScheduleDispatch
    {
        $settings = $this->settingsWithScheduleWebhook();
        $dispatch = $this->monthlyDispatchWithMessage($month, $settings->schedule_webhook_url);

        try {
            $messageIds = $this->client->syncMany(
                $settings->schedule_webhook_url,
                $dispatch->messageIds(),
                $this->messageBuilder->monthlySchedule($month),
            );
        } catch (DiscordMessageNotFoundException) {
            $dispatch->delete();

            throw new RuntimeException('Pesan schedule Discord tidak ditemukan lagi. Status lokal direset, silakan Send to Discord ulang.');
        }

        $webhookHash = DiscordSetting::webhookHash($settings->schedule_webhook_url);

        $dispatch->update([
            'discord_message_id' => $messageIds[0] ?? null,
            'discord_message_ids' => $messageIds,
            'webhook_hash' => $webhookHash,
            'updated_sent_at' => now(),
            'sent_by' => $user?->id,
        ]);

        return $dispatch;
    }

    public function deleteMonthlySchedule(string $month): void
    {
        $settings = $this->settingsWithScheduleWebhook();
        $dispatch = $this->monthlyDispatchWithMessage($month, $settings->schedule_webhook_url);

        try {
            $this->client->deleteMany($settings->schedule_webhook_url, $dispatch->messageIds());
        } catch (DiscordMessageNotFoundException) {
            //
        }

        $dispatch->delete();
    }

    public function syncMonthlySchedule(string $month): ?ScheduleDispatch
    {
        $settings = DiscordSetting::current();

        if (! $settings->auto_schedule_enabled) {
            return null;
        }

        if (! $this->monthHasPublishedEvents($month)) {
            return null;
        }

        $dispatch = ScheduleDispatch::query()
            ->whereDate('month', ScheduleDispatch::monthDate($month))
            ->first();

        if ($dispatch?->isForWebhook($settings->schedule_webhook_url)) {
            return $this->updateMonthlySchedule($month);
        }

        return $this->sendMonthlySchedule($month);
    }

    public function sendEventDetail(Event $event, ?User $user = null): EventDetailDispatch
    {
        $this->ensurePublished($event);
        $settings = $this->settingsWithDetailWebhook();
        $messageIds = $this->client->sendMany($settings->detail_webhook_url, $this->messageBuilder->eventDetail($event));
        $webhookHash = DiscordSetting::webhookHash($settings->detail_webhook_url);

        return EventDetailDispatch::query()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'discord_message_id' => $messageIds[0] ?? null,
                'discord_message_ids' => $messageIds,
                'webhook_hash' => $webhookHash,
                'sent_at' => now(),
                'updated_sent_at' => null,
                'sent_by' => $user?->id,
            ],
        );
    }

    public function updateEventDetail(Event $event, ?User $user = null): EventDetailDispatch
    {
        $this->ensurePublished($event);
        $settings = $this->settingsWithDetailWebhook();
        $dispatch = $this->eventDispatchWithMessage($event, $settings->detail_webhook_url);

        try {
            $messageIds = $this->client->syncMany($settings->detail_webhook_url, $dispatch->messageIds(), $this->messageBuilder->eventDetail($event));
        } catch (DiscordMessageNotFoundException) {
            $dispatch->delete();

            throw new RuntimeException('Pesan detail Discord tidak ditemukan lagi. Status lokal direset, silakan Send Detail ulang.');
        }

        $webhookHash = DiscordSetting::webhookHash($settings->detail_webhook_url);

        $dispatch->update([
            'discord_message_id' => $messageIds[0] ?? null,
            'discord_message_ids' => $messageIds,
            'webhook_hash' => $webhookHash,
            'updated_sent_at' => now(),
            'sent_by' => $user?->id,
        ]);

        return $dispatch;
    }

    public function deleteEventDetail(Event $event): void
    {
        $settings = $this->settingsWithDetailWebhook();
        $dispatch = $this->eventDispatchWithMessage($event, $settings->detail_webhook_url);

        try {
            $this->client->deleteMany($settings->detail_webhook_url, $dispatch->messageIds());
        } catch (DiscordMessageNotFoundException) {
            //
        }

        $dispatch->delete();
    }

    /**
     * @return Collection<int, EventDetailDispatch>
     */
    public function sendDateDetails(string $date, ?User $user = null): Collection
    {
        $settings = $this->settingsWithDetailWebhook();

        return $this->publishedEventsForDate($date)
            ->filter(fn (Event $event) => ! $this->eventDetailSentForWebhook($event, $settings->detail_webhook_url))
            ->map(fn (Event $event) => $this->sendEventDetail($event, $user))
            ->values();
    }

    /**
     * @return Collection<int, EventDetailDispatch>
     */
    public function updateDateDetails(string $date, ?User $user = null): Collection
    {
        $settings = $this->settingsWithDetailWebhook();

        return $this->publishedEventsForDate($date)
            ->filter(fn (Event $event) => $this->eventDetailSentForWebhook($event, $settings->detail_webhook_url))
            ->map(fn (Event $event) => $this->updateEventDetail($event, $user))
            ->values();
    }

    public function deleteDateDetails(string $date): int
    {
        $settings = $this->settingsWithDetailWebhook();

        return $this->publishedEventsForDate($date)
            ->filter(fn (Event $event) => $this->eventDetailSentForWebhook($event, $settings->detail_webhook_url))
            ->each(fn (Event $event) => $this->deleteEventDetail($event))
            ->count();
    }

    /**
     * @return Collection<int, EventDetailDispatch>
     */
    public function syncDateDetails(string $date): Collection
    {
        $settings = DiscordSetting::current();

        if (! $settings->auto_detail_enabled) {
            return collect();
        }

        return $this->publishedEventsForDate($date)
            ->map(fn (Event $event) => $this->eventDetailSentForWebhook($event, $settings->detail_webhook_url)
                ? $this->updateEventDetail($event)
                : $this->sendEventDetail($event))
            ->values();
    }

    public function dateState(string $date): array
    {
        $settings = DiscordSetting::current();
        $events = $this->publishedEventsForDate($date);
        $sentCount = $events->filter(fn (Event $event) => $this->eventDetailSentForWebhook($event, $settings->detail_webhook_url))->count();

        return [
            'published_count' => $events->count(),
            'sent_count' => $sentCount,
            'unsent_count' => max(0, $events->count() - $sentCount),
        ];
    }

    private function settingsWithScheduleWebhook(): DiscordSetting
    {
        $settings = DiscordSetting::current();

        if (! $settings->hasScheduleWebhook()) {
            throw new RuntimeException('Webhook schedule Discord belum diatur.');
        }

        return $settings;
    }

    private function settingsWithDetailWebhook(): DiscordSetting
    {
        $settings = DiscordSetting::current();

        if (! $settings->hasDetailWebhook()) {
            throw new RuntimeException('Webhook detail Discord belum diatur.');
        }

        return $settings;
    }

    private function monthlyDispatchWithMessage(string $month, ?string $webhookUrl): ScheduleDispatch
    {
        $dispatch = ScheduleDispatch::query()
            ->whereDate('month', ScheduleDispatch::monthDate($month))
            ->first();

        if (! $dispatch?->isForWebhook($webhookUrl)) {
            throw new RuntimeException('Schedule bulan ini belum dikirim ke webhook aktif.');
        }

        return $dispatch;
    }

    private function eventDispatchWithMessage(Event $event, ?string $webhookUrl): EventDetailDispatch
    {
        $dispatch = $event->detailDispatch ?: EventDetailDispatch::query()->whereBelongsTo($event)->first();

        if (! $dispatch?->isForWebhook($webhookUrl)) {
            throw new RuntimeException('Detail event ini belum dikirim ke webhook aktif.');
        }

        return $dispatch;
    }

    private function ensurePublished(Event $event): void
    {
        if ($event->status !== Event::STATUS_PUBLISHED) {
            throw new RuntimeException('Draft event tidak bisa dikirim ke Discord.');
        }
    }

    private function eventDetailSentForWebhook(Event $event, ?string $webhookUrl): bool
    {
        return (bool) $event->detailDispatch?->isForWebhook($webhookUrl);
    }

    private function monthHasPublishedEvents(string $month): bool
    {
        return Event::query()
            ->published()
            ->forMonth(ScheduleMonth::parse($month))
            ->exists();
    }

    /**
     * @return Collection<int, Event>
     */
    private function publishedEventsForDate(string $date): Collection
    {
        return Event::query()
            ->with('detailDispatch')
            ->published()
            ->forDate(Carbon::parse($date)->toDateString())
            ->orderBy('start_time')
            ->get();
    }
}
