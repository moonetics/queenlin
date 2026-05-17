<?php

namespace App\Models;

use Database\Factories\ScheduleDispatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'month',
    'discord_message_id',
    'discord_message_ids',
    'webhook_hash',
    'sent_at',
    'updated_sent_at',
    'sent_by',
])]
class ScheduleDispatch extends Model
{
    /** @use HasFactory<ScheduleDispatchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'discord_message_ids' => 'array',
            'sent_at' => 'datetime',
            'updated_sent_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * @return list<string>
     */
    public function messageIds(): array
    {
        $ids = collect($this->discord_message_ids)
            ->filter(fn ($id): bool => is_string($id) && $id !== '')
            ->values()
            ->all();

        if ($ids !== []) {
            return $ids;
        }

        return filled($this->discord_message_id) ? [$this->discord_message_id] : [];
    }

    public function isForWebhook(?string $webhookUrl): bool
    {
        if (! filled($this->discord_message_id) || ! filled($webhookUrl)) {
            return false;
        }

        if (! filled($this->webhook_hash)) {
            return true;
        }

        return hash_equals($this->webhook_hash, (string) DiscordSetting::webhookHash($webhookUrl));
    }

    public static function monthDate(string $month): string
    {
        return Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
    }
}
