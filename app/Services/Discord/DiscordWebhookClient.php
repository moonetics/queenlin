<?php

namespace App\Services\Discord;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class DiscordWebhookClient
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $webhookUrl, array $payload): string
    {
        $response = $this->postPayload($this->withWait($webhookUrl), $payload);

        if ($response->failed()) {
            throw new RuntimeException('Discord webhook send failed.');
        }

        $messageId = $response->json('id');

        if (! is_string($messageId) || $messageId === '') {
            throw new RuntimeException('Discord did not return a message id.');
        }

        return $messageId;
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<string>
     */
    public function sendMany(string $webhookUrl, array $payloads): array
    {
        return array_map(fn (array $payload): string => $this->send($webhookUrl, $payload), $payloads);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $webhookUrl, string $messageId, array $payload): void
    {
        $response = $this->patchPayload($this->messageUrl($webhookUrl, $messageId), $payload);

        if ($response->notFound()) {
            throw new DiscordMessageNotFoundException('Discord message not found.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Discord webhook update failed.');
        }
    }

    public function delete(string $webhookUrl, string $messageId): void
    {
        $response = Http::delete($this->messageUrl($webhookUrl, $messageId));

        if ($response->notFound()) {
            throw new DiscordMessageNotFoundException('Discord message not found.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Discord webhook delete failed.');
        }
    }

    /**
     * @param  list<string>  $messageIds
     * @param  list<array<string, mixed>>  $payloads
     * @return list<string>
     */
    public function syncMany(string $webhookUrl, array $messageIds, array $payloads): array
    {
        $syncedIds = [];

        foreach ($payloads as $index => $payload) {
            if (isset($messageIds[$index])) {
                $this->update($webhookUrl, $messageIds[$index], $payload);
                $syncedIds[] = $messageIds[$index];

                continue;
            }

            $syncedIds[] = $this->send($webhookUrl, $payload);
        }

        foreach (array_slice($messageIds, count($payloads)) as $messageId) {
            $this->delete($webhookUrl, $messageId);
        }

        return $syncedIds;
    }

    /**
     * @param  list<string>  $messageIds
     */
    public function deleteMany(string $webhookUrl, array $messageIds): void
    {
        foreach ($messageIds as $messageId) {
            $this->delete($webhookUrl, $messageId);
        }
    }

    private function withWait(string $webhookUrl): string
    {
        $separator = str_contains($webhookUrl, '?') ? '&' : '?';

        return $webhookUrl.$separator.'wait=true';
    }

    private function messageUrl(string $webhookUrl, string $messageId): string
    {
        return Str::before($webhookUrl, '?').'/messages/'.$messageId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postPayload(string $url, array $payload): mixed
    {
        if (! isset($payload['files']) || ! is_array($payload['files'])) {
            return Http::asJson()->post($url, $payload);
        }

        return $this->sendMultipartPayload('post', $url, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function patchPayload(string $url, array $payload): mixed
    {
        if (! isset($payload['files']) || ! is_array($payload['files'])) {
            return Http::asJson()->patch($url, $payload);
        }

        return $this->sendMultipartPayload('patch', $url, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendMultipartPayload(string $method, string $url, array $payload): mixed
    {
        $files = $payload['files'];
        unset($payload['files']);

        $request = Http::asMultipart();
        $attachedCount = 0;

        foreach ($files as $index => $file) {
            if (! is_array($file) || ! isset($file['path']) || ! is_string($file['path']) || ! is_file($file['path'])) {
                continue;
            }

            $contents = file_get_contents($file['path']);

            if ($contents === false) {
                continue;
            }

            $attachedCount++;
            $request = $request->attach(
                'files['.$index.']',
                $contents,
                is_string($file['name'] ?? null) ? $file['name'] : basename($file['path']),
                ['Content-Type' => is_string($file['mime'] ?? null) ? $file['mime'] : 'application/octet-stream'],
            );
        }

        if ($attachedCount === 0) {
            unset($payload['attachments']);

            return Http::asJson()->{$method}($url, $payload);
        }

        return $request->{$method}($url, [
            'payload_json' => $this->encodePayload($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encodePayload(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Discord webhook payload encoding failed.', previous: $exception);
        }
    }
}
