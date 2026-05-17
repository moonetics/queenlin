<?php

namespace App\Services\Discord;

use App\Models\Event;
use App\Services\DiscordSchedulePayloadBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiscordMessageBuilder
{
    private const MAX_CONTENT_LENGTH = 1900;

    private const DATE_SEPARATOR = '━━━━━━━━━━━━━━━━━━━━';

    public function __construct(private readonly DiscordSchedulePayloadBuilder $schedulePayloadBuilder) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function monthlySchedule(string $month): array
    {
        $payload = $this->schedulePayloadBuilder->forMonth($month);
        $monthDate = Carbon::parse($payload['month'])->locale('id');
        $header = '# 📅 SCHEDULES '.Str::upper($monthDate->translatedFormat('F Y'));

        if ($payload['events'] === []) {
            return $this->contentPayloads($this->splitText($header."\n\nBelum ada event published."));
        }

        $dateBlocks = collect($payload['events'])
            ->groupBy('date')
            ->flatMap(fn (Collection $events, string $date): array => $this->dateBlocks($date, $events))
            ->values()
            ->all();

        return $this->contentPayloads($this->splitScheduleBlocks($header, $dateBlocks));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eventDetail(Event $event): array
    {
        $description = trim((string) $event->description);
        $content = $this->withEveryoneAtBottom($description !== '' ? $description : 'Belum ada detail event.');
        $payloads = $this->contentPayloads($this->splitText($content), ['everyone']);

        return $this->withPosterAttachment($payloads, $event);
    }

    /**
     * @param  Collection<int, array<string, string|null>>  $events
     * @return list<string>
     */
    private function dateBlocks(string $date, Collection $events): array
    {
        $dateHeader = '## '.$this->dateIcon($date).' '.Str::upper(Carbon::parse($date)->locale('id')->translatedFormat('d F Y'));
        $eventBlocks = $events
            ->map(fn (array $event): string => $this->eventBlock($event))
            ->values()
            ->all();
        $dateBlock = $dateHeader."\n".$this->joinEventBlocks($eventBlocks);

        if (mb_strlen($dateBlock) <= self::MAX_CONTENT_LENGTH) {
            return [$dateBlock];
        }

        $blocks = [];
        $current = $dateHeader;

        foreach ($eventBlocks as $eventBlock) {
            if (mb_strlen($eventBlock) + mb_strlen($dateHeader) + 2 > self::MAX_CONTENT_LENGTH) {
                foreach ($this->splitText($dateHeader."\n".$eventBlock) as $chunk) {
                    $blocks[] = $chunk;
                }

                continue;
            }

            $candidate = $current === $dateHeader
                ? $dateHeader."\n".$eventBlock
                : $current."\n\n".$eventBlock;

            if (mb_strlen($candidate) > self::MAX_CONTENT_LENGTH) {
                $blocks[] = $current;
                $current = $dateHeader."\n".$eventBlock;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== $dateHeader) {
            $blocks[] = $current;
        }

        return $blocks;
    }

    /**
     * @param  array<string, string|null>  $event
     */
    private function eventBlock(array $event): string
    {
        $cancelMarker = $event['progress_status'] === Event::PROGRESS_CANCELED ? ' ❌' : '';

        return implode("\n", [
            '🕖 **'.$this->startTime($event['time']).' WIB** ┃ **'.$this->textOrDash($event['map_name']).'**'.$cancelMarker,
            '🎉 '.$this->textOrDash($event['theme']),
            '💸 Prizepool: **'.$this->discordPrizepool($event['prizepool']).'**',
        ]);
    }

    /**
     * @param  list<string>  $eventBlocks
     */
    private function joinEventBlocks(array $eventBlocks): string
    {
        return implode("\n\n", $eventBlocks);
    }

    /**
     * @param  list<string>  $dateBlocks
     * @return list<string>
     */
    private function splitScheduleBlocks(string $header, array $dateBlocks): array
    {
        $messages = [];
        $current = $header;
        $hasDateBlock = false;

        foreach ($dateBlocks as $dateBlock) {
            $separator = $hasDateBlock ? "\n\n".self::DATE_SEPARATOR."\n\n" : "\n\n";
            $candidate = $current.$separator.$dateBlock;

            if (mb_strlen($candidate) <= self::MAX_CONTENT_LENGTH) {
                $current = $candidate;
                $hasDateBlock = true;

                continue;
            }

            if ($hasDateBlock) {
                $messages[] = $current;
                $current = $dateBlock;
                $hasDateBlock = true;

                continue;
            }

            foreach ($this->splitText($candidate) as $chunk) {
                $messages[] = $chunk;
            }

            $current = '';
            $hasDateBlock = false;
        }

        if (trim($current) !== '') {
            $messages[] = $current;
        }

        return $messages;
    }

    /**
     * @return list<string>
     */
    private function splitText(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['-'];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split("/(\r\n|\n|\r)/", $text) ?: [] as $line) {
            $candidate = $current === '' ? $line : $current."\n".$line;

            if (mb_strlen($candidate) <= self::MAX_CONTENT_LENGTH) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
            }

            while (mb_strlen($line) > self::MAX_CONTENT_LENGTH) {
                $chunks[] = mb_substr($line, 0, self::MAX_CONTENT_LENGTH);
                $line = mb_substr($line, self::MAX_CONTENT_LENGTH);
            }

            $current = $line;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks !== [] ? $chunks : ['-'];
    }

    /**
     * @param  list<string>  $contents
     * @return list<array<string, mixed>>
     */
    private function contentPayloads(array $contents, array $allowedMentionParse = []): array
    {
        return array_map(fn (string $content): array => [
            'content' => $content,
            'allowed_mentions' => ['parse' => $allowedMentionParse],
        ], $contents);
    }

    private function withEveryoneAtBottom(string $text): string
    {
        $text = trim((string) preg_replace('/[ \t]*@everyone\b[ \t]*/i', '', $text));
        $text = $text !== '' ? $text : 'Belum ada detail event.';

        return $text."\n\n@everyone";
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    private function withPosterAttachment(array $payloads, Event $event): array
    {
        $poster = $event->poster;

        if (! is_string($poster) || trim($poster) === '' || $payloads === []) {
            return $payloads;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($poster)) {
            return $payloads;
        }

        $filename = basename($poster);

        $payloads[0]['attachments'] = [
            [
                'id' => 0,
                'filename' => $filename,
            ],
        ];
        $payloads[0]['files'] = [
            [
                'path' => $disk->path($poster),
                'name' => $filename,
                'mime' => $disk->mimeType($poster) ?: 'application/octet-stream',
            ],
        ];

        return $payloads;
    }

    private function dateIcon(string $date): string
    {
        return Carbon::parse($date)->toDateString() < Date::now('Asia/Jakarta')->toDateString() ? '✅' : '📌';
    }

    private function startTime(?string $time): string
    {
        return Str::of((string) $time)->before('-')->replace(':', '.')->toString();
    }

    private function discordPrizepool(?string $prizepool): string
    {
        $label = trim((string) $prizepool);

        if ($label === '' || $label === '-') {
            return '-';
        }

        $label = trim((string) preg_replace('/^Rp\s*/i', '', $label));

        return $label.' IDR';
    }

    private function textOrDash(?string $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '-';
    }
}
