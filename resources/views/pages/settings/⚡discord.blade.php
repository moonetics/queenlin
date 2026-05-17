<?php

use App\Models\DiscordSetting;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('components.layouts.admin', ['title' => 'Settings'])]
#[Title('Discord settings')]
class extends Component {
    public string $schedule_webhook_url = '';
    public string $detail_webhook_url = '';
    public bool $auto_schedule_enabled = true;
    public bool $auto_detail_enabled = true;

    public function mount(): void
    {
        $settings = DiscordSetting::current();

        $this->schedule_webhook_url = (string) $settings->schedule_webhook_url;
        $this->detail_webhook_url = (string) $settings->detail_webhook_url;
        $this->auto_schedule_enabled = $settings->auto_schedule_enabled;
        $this->auto_detail_enabled = $settings->auto_detail_enabled;
    }

    public function updateDiscordSettings(): void
    {
        $validated = $this->validate([
            'schedule_webhook_url' => ['nullable', 'url', 'regex:/^https:\/\/(?:canary\.|ptb\.)?discord(?:app)?\.com\/api\/webhooks\/\d+\/[A-Za-z0-9._-]+/'],
            'detail_webhook_url' => ['nullable', 'url', 'regex:/^https:\/\/(?:canary\.|ptb\.)?discord(?:app)?\.com\/api\/webhooks\/\d+\/[A-Za-z0-9._-]+/'],
            'auto_schedule_enabled' => ['boolean'],
            'auto_detail_enabled' => ['boolean'],
        ], [
            'schedule_webhook_url.regex' => 'URL webhook schedule harus berupa Discord webhook URL.',
            'detail_webhook_url.regex' => 'URL webhook detail harus berupa Discord webhook URL.',
        ]);

        DiscordSetting::current()->update($validated);

        Flux::toast(variant: 'success', text: 'Discord settings updated.');
    }
}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('Discord settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Discord')" :subheading="__('Atur webhook schedule bulanan dan detail event harian')">
        <form wire:submit="updateDiscordSettings" class="my-6 w-full space-y-6">
            <flux:input wire:model="schedule_webhook_url" :label="__('Schedule webhook URL')" type="url" autocomplete="off" placeholder="https://discord.com/api/webhooks/..." />

            <flux:input wire:model="detail_webhook_url" :label="__('Detail event webhook URL')" type="url" autocomplete="off" placeholder="https://discord.com/api/webhooks/..." />

            <flux:checkbox wire:model="auto_schedule_enabled" :label="__('Auto send monthly schedule on day 1 at 00:00')" />

            <flux:checkbox wire:model="auto_detail_enabled" :label="__('Auto send event details on event date at 00:00')" />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Save Discord Settings') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
