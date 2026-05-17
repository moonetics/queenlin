<?php

use App\Models\Event;
use App\Models\ManualFullDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

test('homepage can be rendered', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Queenlin')
        ->assertSee('queenlin-theme-toggle', false)
        ->assertSee('Managed by Squad Limpul')
        ->assertSee((string) now()->year);
});

test('schedule shows empty state when selected date has no published events', function () {
    $date = now()->startOfMonth()->toDateString();

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('queenlin-theme-toggle', false)
        ->assertSee('No events yet')
        ->assertSee('Belum ada event pada tanggal ini')
        ->assertDontSee('Belum ada event publish')
        ->assertSee('Tersedia');
});

test('schedule only shows published events for selected date', function () {
    $date = now()->toDateString();

    Event::factory()->create([
        'title' => 'Published Cup',
        'event_date' => $date,
        'status' => Event::STATUS_PUBLISHED,
    ]);

    Event::factory()->draft()->create([
        'title' => 'Hidden Draft',
        'event_date' => $date,
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Published Cup')
        ->assertDontSee('Hidden Draft');
});

test('schedule density is based on total event duration', function () {
    $date = now()->toDateString();

    Event::factory()->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '18:00',
    ]);

    Event::factory()->create([
        'event_date' => $date,
        'start_time' => '19:00',
        'end_time' => '00:00',
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Penuh')
        ->assertDontSee('8j 0m');
});

test('schedule selected event is controlled by event query parameter', function () {
    $date = now()->toDateString();

    $first = Event::factory()->create([
        'title' => 'First Event',
        'map_name' => 'Erangel',
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '17:00',
        'prizepool' => '50000',
    ]);

    $second = Event::factory()->create([
        'title' => 'Second Event',
        'map_name' => 'Miramar',
        'event_date' => $date,
        'start_time' => '18:00',
        'end_time' => '20:00',
        'prizepool' => '100000',
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date, 'event' => $second->id]))
        ->assertOk()
        ->assertSee('Erangel')
        ->assertSee('Miramar')
        ->assertSee('15:00-17:00 WIB')
        ->assertSee('18:00-20:00 WIB')
        ->assertSee('Second Event')
        ->assertSee('Rp 100.000')
        ->assertDontSee('Rp 50.000');

    expect($first->exists)->toBeTrue();
});

test('schedule calendar shows published event count badge', function () {
    $date = now()->toDateString();

    Event::factory()->count(3)->sequence(
        ['start_time' => '15:00', 'end_time' => '16:00'],
        ['start_time' => '16:00', 'end_time' => '17:00'],
        ['start_time' => '17:00', 'end_time' => '18:00'],
    )->create([
        'event_date' => $date,
        'status' => Event::STATUS_PUBLISHED,
    ]);

    Event::factory()->draft()->create([
        'event_date' => $date,
        'start_time' => '18:00',
        'end_time' => '19:00',
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('>3</span>', false);
});

test('schedule calendar uses monday first weekday labels', function () {
    $this->get(route('schedule', ['month' => '2026-05', 'date' => '2026-05-09']))
        ->assertOk()
        ->assertSeeInOrder([
            '>Sen</div>',
            '>Sel</div>',
            '>Rab</div>',
            '>Kam</div>',
            '>Jum</div>',
            '>Sab</div>',
            '>Min</div>',
        ], false)
        ->assertSee('Saturday, 09 May 2026');
});

test('manual full date appears full on public schedule', function () {
    $date = now()->toDateString();

    ManualFullDate::factory()->create([
        'event_date' => $date,
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Penuh')
        ->assertSee('Belum ada event pada tanggal ini');
});

test('manual libur date appears as libur on public schedule', function () {
    $date = now()->toDateString();

    ManualFullDate::factory()->create([
        'event_date' => $date,
        'status' => ManualFullDate::STATUS_OFF,
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Libur')
        ->assertSee('Queenlin sedang libur pada tanggal ini.')
        ->assertDontSee('Slot masih tersedia');
});

test('schedule calendar renders large background day number', function () {
    $date = now()->toDateString();

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('aria-hidden="true"', false)
        ->assertSee('text-5xl', false);
});

test('schedule shows renamed busy label', function () {
    $date = now()->toDateString();

    Event::factory()->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '20:00',
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Padat')
        ->assertDontSee('Cukup Padat')
        ->assertDontSee('Lumayan Padat');
});

test('schedule poster has lightbox zoom controls', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs('event-posters', UploadedFile::fake()->image('poster.jpg'), 'poster.webp');

    $date = now()->toDateString();

    Event::factory()->create([
        'title' => 'Zoom Event',
        'poster' => 'event-posters/poster.webp',
        'event_date' => $date,
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Buka poster event')
        ->assertSee('Zoom In')
        ->assertSee('Zoom Out')
        ->assertSee('Reset')
        ->assertSee('@wheel.prevent', false)
        ->assertSee('overflow-hidden', false)
        ->assertDontSee('overflow-auto', false);
});

test('schedule shows event progress badges for done and canceled events', function () {
    $date = now()->toDateString();

    Event::factory()->create([
        'title' => 'Finished Event',
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '16:00',
        'progress_status' => Event::PROGRESS_DONE,
    ]);

    $canceled = Event::factory()->create([
        'title' => 'Canceled Event',
        'event_date' => $date,
        'start_time' => '16:00',
        'end_time' => '17:00',
        'progress_status' => Event::PROGRESS_CANCELED,
    ]);

    $this->get(route('schedule', ['month' => now()->format('Y-m'), 'date' => $date, 'event' => $canceled->id]))
        ->assertOk()
        ->assertSee('Finished Event')
        ->assertSee('Selesai')
        ->assertDontSee('✓', false)
        ->assertSee('Canceled Event')
        ->assertSee('Cancel');
});

test('schedule renders when the app uses immutable dates', function () {
    Date::useClass(CarbonImmutable::class);

    try {
        $this->get(route('schedule'))
            ->assertOk()
            ->assertSee('Pilih tanggal event');
    } finally {
        Date::useDefault();
    }
});
