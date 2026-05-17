<?php

use App\Models\User;
use App\Models\DiscordSetting;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('queenlin-theme-toggle', false)
        ->assertSee('Profile')
        ->assertSee('Discord')
        ->assertSee('Security')
        ->assertDontSee('>Appearance</a>', false)
        ->assertDontSee(route('appearance.edit'), false);
});

test('settings navigation uses full page links', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('discord.edit'))
        ->assertOk()
        ->assertSee(route('profile.edit'), false)
        ->assertSee(route('discord.edit'), false)
        ->assertSee(route('security.edit'), false)
        ->assertDontSee('wire:navigate', false);
});

test('settings route redirects to profile settings', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('settings'))
        ->assertRedirect(route('profile.edit', absolute: false));
});

test('discord settings page can be rendered and updated', function () {
    $user = User::factory()->create();

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/111/existing-schedule-token',
        'detail_webhook_url' => 'https://discord.com/api/webhooks/222/existing-detail-token',
        'auto_schedule_enabled' => true,
        'auto_detail_enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('discord.edit'))
        ->assertOk()
        ->assertSee('Discord')
        ->assertSee('Schedule webhook URL')
        ->assertSee('existing-schedule-token', false)
        ->assertSee('existing-detail-token', false);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.discord')
        ->set('schedule_webhook_url', 'https://discord.com/api/webhooks/123/schedule-token')
        ->set('detail_webhook_url', 'https://discord.com/api/webhooks/456/detail-token')
        ->set('auto_schedule_enabled', false)
        ->set('auto_detail_enabled', true)
        ->call('updateDiscordSettings');

    $response->assertHasNoErrors();

    $settings = DiscordSetting::current();

    expect($settings->schedule_webhook_url)->toBe('https://discord.com/api/webhooks/123/schedule-token')
        ->and($settings->detail_webhook_url)->toBe('https://discord.com/api/webhooks/456/detail-token')
        ->and($settings->auto_schedule_enabled)->toBeFalse()
        ->and($settings->auto_detail_enabled)->toBeTrue();
});

test('discord settings reject non discord webhook urls', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.discord')
        ->set('schedule_webhook_url', 'https://example.com/not-discord')
        ->call('updateDiscordSettings')
        ->assertHasErrors(['schedule_webhook_url']);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
