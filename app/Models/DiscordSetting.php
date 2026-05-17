<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'schedule_webhook_url',
    'detail_webhook_url',
    'auto_schedule_enabled',
    'auto_detail_enabled',
])]
class DiscordSetting extends Model
{
    protected function casts(): array
    {
        return [
            'schedule_webhook_url' => 'encrypted',
            'detail_webhook_url' => 'encrypted',
            'auto_schedule_enabled' => 'boolean',
            'auto_detail_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'auto_schedule_enabled' => true,
            'auto_detail_enabled' => true,
        ]);
    }

    public function hasScheduleWebhook(): bool
    {
        return filled($this->schedule_webhook_url);
    }

    public function hasDetailWebhook(): bool
    {
        return filled($this->detail_webhook_url);
    }

    public static function webhookHash(?string $webhookUrl): ?string
    {
        if (! filled($webhookUrl)) {
            return null;
        }

        $normalized = Str::of($webhookUrl)
            ->trim()
            ->before('?')
            ->rtrim('/')
            ->toString();

        return hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
