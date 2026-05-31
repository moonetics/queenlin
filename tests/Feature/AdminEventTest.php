<?php

use App\Models\DiscordSetting;
use App\Models\Event;
use App\Models\EventDetailDispatch;
use App\Models\ManualFullDate;
use App\Models\ScheduleDispatch;
use App\Models\User;
use App\Services\Discord\DiscordMessageBuilder;
use App\Services\DiscordSchedulePayloadBuilder;
use App\Support\ScheduleMonth;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

if (! function_exists('adminEventDateUrl')) {
    function adminEventDateUrl(string $date): string
    {
        return route('admin.events.index', ['month' => substr($date, 0, 7), 'date' => $date]);
    }
}

test('admin can render guided create event form', function () {
    $user = User::factory()->create();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->addDay()->toDateString(),
        'start_time' => '15:00',
        'end_time' => '23:00',
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('Jadwal Event')
        ->assertDontSee('Status event')
        ->assertSee('name="schedule_month"', false)
        ->assertSee('name="event_date"', false)
        ->assertSee('x-model="selectedDate"', false)
        ->assertSee('dateMonths', false)
        ->assertSee('eventCount', false)
        ->assertSee('<template x-for="[date, slot]', false)
        ->assertSee('custom-date-dropdown', false)
        ->assertSee('custom-time-dropdown', false)
        ->assertDontSee('Tandai Penuh')
        ->assertDontSee('Tandai Libur')
        ->assertSee('Pilih tanggal dulu')
        ->assertSee('Slot tanggal ini')
        ->assertSee('Jam terisi')
        ->assertSee('Slot merah tidak bisa dipilih.')
        ->assertSee('Jam mulai')
        ->assertSee('Jam selesai')
        ->assertSee('Poster Event (Opsional)')
        ->assertSee('Maksimal 2 MB')
        ->assertSee('name="start_time"', false)
        ->assertSee('name="end_time"', false)
        ->assertSee('15:05')
        ->assertDontSee('Visual ketersediaan jam')
        ->assertDontSee('type="time"', false)
        ->assertSee('border-emerald-200', false)
        ->assertSee('border-red-200', false);
});

test('admin create form keeps a valid old event date in the native date select state', function () {
    $user = User::factory()->create();
    $date = now()->addDays(3)->toDateString();

    $this->actingAs($user)
        ->withSession(['_old_input' => [
            'event_date' => $date,
            'schedule_month' => substr($date, 0, 7),
        ]])
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('name="event_date"', false)
        ->assertSee('value="'.$date.'"', false)
        ->assertSee('selectedDate: \''.$date.'\'', false)
        ->assertSee('x-ref="eventDateInput"', false);
});

test('admin can create a published event with optimized poster', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $poster = UploadedFile::fake()->image('poster.jpg', 900, 1200);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Queenlin Scrim',
            'poster' => $poster,
            'theme' => 'Soft Green Battle',
            'map_name' => 'Erangel',
            'prizepool' => 'Rp 500.000',
            'event_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '18:00',
            'description' => 'Open lobby caster event.',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl(now()->toDateString()));

    $event = Event::query()->firstOrFail();

    expect($event->poster)->toEndWith('.webp');
    expect($event->prizepool)->toBe('500000')
        ->and($event->formattedPrizepool())->toBe('Rp 500.000');
    Storage::disk('public')->assertExists($event->poster);
});

test('admin can create a published event without poster', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Queenlin No Poster',
            'theme' => 'Soft Green Battle',
            'map_name' => 'Erangel',
            'prizepool' => 'Rp 500.000',
            'event_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '18:00',
            'description' => 'Open lobby caster event.',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl(now()->toDateString()));

    $event = Event::query()->firstOrFail();

    expect($event->poster)->toBeNull();
});

test('event validation shows Indonesian message when event date is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Missing Date',
            'theme' => 'No Date',
            'map_name' => 'Erangel',
            'event_date' => '',
            'start_time' => '15:00',
            'end_time' => '18:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['event_date' => 'Tanggal event wajib dipilih.']);
});

test('event validation shows Indonesian message when event date format is invalid', function (mixed $eventDate) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Invalid Date',
            'theme' => 'Bad Date',
            'map_name' => 'Erangel',
            'event_date' => $eventDate,
            'start_time' => '15:00',
            'end_time' => '18:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['event_date' => 'Tanggal event tidak valid. Pilih tanggal dari daftar yang tersedia.']);
})->with([
    'zero' => 0,
    'localized format' => '08/05/2026',
    'invalid calendar date' => '2026-02-31',
]);

test('event validation normalizes browser submitted date labels as a fallback', function () {
    $user = User::factory()->create();
    $date = now()->addDays(4);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Label Date Event',
            'theme' => 'Browser Label',
            'map_name' => 'Erangel',
            'event_date' => $date->format('d M Y').' - Sisa 9j 0m',
            'start_time' => '15:00',
            'end_time' => '18:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl($date->toDateString()));

    $this->assertDatabaseHas('events', [
        'title' => 'Label Date Event',
        'event_date' => $date->toDateString().' 00:00:00',
    ]);
});

test('poster validation uses a clear poster error without rejecting a valid selected date', function () {
    $user = User::factory()->create();
    $date = now()->addDays(2)->toDateString();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Large Poster Event',
            'poster' => UploadedFile::fake()->image('large-poster.jpg', 900, 1200)->size(6000),
            'theme' => 'Large Upload',
            'map_name' => 'Erangel',
            'event_date' => $date,
            'start_time' => '15:00',
            'end_time' => '18:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['poster' => 'Poster event maksimal 2 MB.'])
        ->assertSessionDoesntHaveErrors(['event_date'])
        ->assertSessionHasInput('event_date', $date);
});

test('admin can update an event and replace poster', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'creator')->create([
        'poster' => 'event-posters/old.webp',
    ]);

    Storage::disk('public')->put('event-posters/old.webp', 'old');

    $this->actingAs($user)
        ->put(route('admin.events.update', $event), [
            'title' => 'Updated Event',
            'poster' => UploadedFile::fake()->image('poster.png', 800, 1000),
            'theme' => 'Updated Theme',
            'map_name' => 'Miramar',
            'prizepool' => '',
            'event_date' => now()->toDateString(),
            'start_time' => '16:00',
            'end_time' => '00:00',
            'description' => 'Updated description.',
            'status' => Event::STATUS_DRAFT,
        ])
        ->assertRedirect(adminEventDateUrl(now()->toDateString()));

    $event->refresh();

    expect($event->title)->toBe('Updated Event')
        ->and($event->status)->toBe(Event::STATUS_DRAFT)
        ->and($event->poster)->not->toBe('event-posters/old.webp');

    Storage::disk('public')->assertMissing('event-posters/old.webp');
    Storage::disk('public')->assertExists($event->poster);
});

test('admin can delete an event and its poster', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'creator')->create([
        'poster' => 'event-posters/delete.webp',
    ]);

    Storage::disk('public')->put('event-posters/delete.webp', 'poster');

    $this->actingAs($user)
        ->delete(route('admin.events.destroy', $event))
        ->assertRedirect(adminEventDateUrl($event->event_date->toDateString()));

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
    Storage::disk('public')->assertMissing('event-posters/delete.webp');
});

test('event validation rejects invalid time ranges', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Invalid Event',
            'theme' => 'Late',
            'map_name' => 'Sanhok',
            'event_date' => now()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '13:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['start_time', 'end_time']);
});

test('event validation rejects overlapping event slots', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '17:00',
        'status' => Event::STATUS_DRAFT,
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Overlap Event',
            'theme' => 'Overlap',
            'map_name' => 'Sanhok',
            'event_date' => $date,
            'start_time' => '16:00',
            'end_time' => '18:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['start_time']);
});

test('admin can create event with minute level times', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Minute Slot',
            'theme' => 'Precise Time',
            'map_name' => 'Erangel',
            'event_date' => now()->toDateString(),
            'start_time' => '15:30',
            'end_time' => '17:15',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl(now()->toDateString()));

    $this->assertDatabaseHas('events', [
        'title' => 'Minute Slot',
        'start_time' => '15:30',
        'end_time' => '17:15',
    ]);
});

test('admin can create event with minimum five minute duration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Five Minute Slot',
            'theme' => 'Short Cast',
            'map_name' => 'Erangel',
            'event_date' => now()->toDateString(),
            'start_time' => '15:00',
            'end_time' => '15:05',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl(now()->toDateString()));

    $this->assertDatabaseHas('events', [
        'title' => 'Five Minute Slot',
        'start_time' => '15:00',
        'end_time' => '15:05',
    ]);
});

test('event validation rejects durations under five minutes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Too Short',
            'theme' => 'Short',
            'map_name' => 'Livik',
            'event_date' => now()->toDateString(),
            'start_time' => '16:15',
            'end_time' => '16:19',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['end_time']);
});

test('event create ignores submitted progress status and starts as scheduled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Progress Event',
            'theme' => 'Progress',
            'map_name' => 'Erangel',
            'event_date' => now()->addDays(3)->toDateString(),
            'start_time' => '15:00',
            'end_time' => '15:05',
            'status' => Event::STATUS_PUBLISHED,
            'progress_status' => Event::PROGRESS_DONE,
        ])
        ->assertRedirect(adminEventDateUrl(now()->addDays(3)->toDateString()));

    $this->assertDatabaseHas('events', [
        'title' => 'Progress Event',
        'progress_status' => Event::PROGRESS_SCHEDULED,
    ]);
});

test('event validation rejects overlapping minute level ranges', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '15:30',
        'end_time' => '17:15',
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Minute Overlap',
            'theme' => 'Overlap',
            'map_name' => 'Miramar',
            'event_date' => $date,
            'start_time' => '17:00',
            'end_time' => '18:30',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['start_time']);
});

test('event validation allows non overlapping events on the same date', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '17:00',
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Next Slot',
            'theme' => 'Clean Slot',
            'map_name' => 'Vikendi',
            'event_date' => $date,
            'start_time' => '17:00',
            'end_time' => '19:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl($date));

    $this->assertDatabaseHas('events', ['title' => 'Next Slot']);
});

test('event validation allows dates with a remaining five minute slot', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '23:00',
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Last Half Hour',
            'theme' => 'Still Available',
            'map_name' => 'Livik',
            'event_date' => $date,
            'start_time' => '23:00',
            'end_time' => '23:05',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl($date));

    $this->assertDatabaseHas('events', ['title' => 'Last Half Hour']);
});

test('event validation rejects dates with no remaining five minute slot', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '23:56',
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Closed Date',
            'theme' => 'Too Tight',
            'map_name' => 'Livik',
            'event_date' => $date,
            'start_time' => '23:55',
            'end_time' => '00:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['event_date']);
});

test('admin can toggle manual full date and block new bookings', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    $this->actingAs($user)
        ->post(route('admin.manual-full-dates.toggle'), ['event_date' => $date])
        ->assertRedirect();

    $this->assertDatabaseHas('manual_full_dates', [
        'event_date' => $date.' 00:00:00',
        'status' => ManualFullDate::STATUS_FULL,
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Blocked Event',
            'theme' => 'Manual Full',
            'map_name' => 'Livik',
            'event_date' => $date,
            'start_time' => '15:00',
            'end_time' => '15:05',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['event_date']);

    $this->actingAs($user)
        ->post(route('admin.manual-full-dates.toggle'), ['event_date' => $date])
        ->assertRedirect();

    $this->assertDatabaseMissing('manual_full_dates', ['event_date' => $date.' 00:00:00']);
});

test('admin form shows manual full date state in dropdown data', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    ManualFullDate::factory()->for($user, 'marker')->create([
        'event_date' => $date,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('Penuh manual')
        ->assertDontSee('Buka Penuh')
        ->assertDontSee('Tandai Penuh')
        ->assertSee('manualFull', false);
});

test('admin can mark a date as libur and block new bookings', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    $this->actingAs($user)
        ->post(route('admin.manual-full-dates.toggle'), [
            'event_date' => $date,
            'status' => ManualFullDate::STATUS_OFF,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('manual_full_dates', [
        'event_date' => $date.' 00:00:00',
        'status' => ManualFullDate::STATUS_OFF,
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.store'), [
            'title' => 'Blocked Libur Event',
            'theme' => 'Off Day',
            'map_name' => 'Livik',
            'event_date' => $date,
            'start_time' => '15:00',
            'end_time' => '15:05',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertSessionHasErrors(['event_date']);
});

test('admin cannot mark a date as libur when it has events', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);

    $this->actingAs($user)
        ->post(route('admin.manual-full-dates.toggle'), [
            'event_date' => $date,
            'status' => ManualFullDate::STATUS_OFF,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Tanggal ini masih punya event, jadi belum bisa ditandai libur.');

    $this->assertDatabaseMissing('manual_full_dates', [
        'event_date' => $date.' 00:00:00',
    ]);
});

test('admin form shows libur date state in dropdown data', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    ManualFullDate::factory()->for($user, 'marker')->create([
        'event_date' => $date,
        'status' => ManualFullDate::STATUS_OFF,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.create'))
        ->assertOk()
        ->assertSee('Libur')
        ->assertDontSee('Buka Libur')
        ->assertDontSee('Tandai Libur')
        ->assertSee('manualOff', false);
});

test('admin dashboard defaults to today and auto filters without reset button', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    $this->actingAs($user)
        ->get(route('admin.events.index'))
        ->assertOk()
        ->assertSee('Status Tanggal')
        ->assertSee($date)
        ->assertSee('admin-month-dropdown', false)
        ->assertSee('admin-date-dropdown', false)
        ->assertSee('window.location.assign', false)
        ->assertDontSee('$refs.filterForm.submit()', false)
        ->assertDontSee('type="month"', false)
        ->assertDontSee('type="date"', false)
        ->assertDontSee('Filter')
        ->assertDontSee('Reset')
        ->assertSee('Tandai Penuh')
        ->assertSee('Tandai Libur')
        ->assertSee('name="date" value="'.$date.'"', false);
});

test('admin schedule month parsing does not overflow from thirty first day', function () {
    $user = User::factory()->create();

    $this->travelTo('2026-05-31 10:00:00');

    try {
        $this->actingAs($user)
            ->get(route('admin.events.index', ['month' => '2026-06']))
            ->assertOk()
            ->assertSee('June 2026')
            ->assertSee('30 tanggal')
            ->assertSee('01 Jun 2026')
            ->assertDontSee('01 Jul 2026')
            ->assertDontSee('01 July 2026');

        expect(ScheduleDispatch::monthDate('2026-06'))->toBe('2026-06-01')
            ->and(app(DiscordSchedulePayloadBuilder::class)->forMonth('2026-06')['title'])->toBe('Jadwal Queenlin - June 2026');
        expect(fn () => ScheduleMonth::parse('2026-13'))->toThrow(InvalidArgumentException::class);
    } finally {
        $this->travelBack();
    }
});

test('admin dashboard month dropdown shows event counts for each month', function () {
    $user = User::factory()->create();
    $nextMonth = now()->copy()->addMonthNoOverflow()->startOfMonth();

    Event::factory()->count(2)->for($user, 'creator')->create([
        'event_date' => $nextMonth->copy()->addDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index'))
        ->assertOk()
        ->assertSee($nextMonth->translatedFormat('F Y'))
        ->assertSee('2 event');
});

test('admin dashboard shows manual date controls for selected date', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Status Tanggal')
        ->assertSee('Normal')
        ->assertSee('1 event')
        ->assertSee('lg:items-center', false)
        ->assertSee('lg:self-center', false)
        ->assertSee('admin-month-dropdown', false)
        ->assertSee('admin-date-dropdown', false)
        ->assertSee('Slot tanggal:')
        ->assertSee('window.location.assign', false)
        ->assertDontSee('$refs.filterForm.submit()', false)
        ->assertSee('Tandai Penuh')
        ->assertSee('Tandai Libur')
        ->assertSee('name="date" value="'.$date.'"', false);
});

test('manual date toggle returns to selected admin date without old input', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();
    $month = now()->format('Y-m');

    $this->actingAs($user)
        ->post(route('admin.manual-full-dates.toggle'), [
            'month' => $month,
            'event_date' => $date,
            'status' => ManualFullDate::STATUS_FULL,
        ])
        ->assertRedirect(route('admin.events.index', ['month' => $month, 'date' => $date]))
        ->assertSessionMissing('_old_input');

    $this->assertDatabaseHas('manual_full_dates', [
        'event_date' => $date.' 00:00:00',
        'status' => ManualFullDate::STATUS_FULL,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => $month, 'date' => $date]))
        ->assertOk()
        ->assertSee('Penuh manual')
        ->assertSee('Buka Penuh');
});

test('admin edit form ignores invalid old date input after redirects', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['_old_input' => ['event_date' => 0, 'schedule_month' => 0]])
        ->get(route('admin.events.edit', $event))
        ->assertOk()
        ->assertSee($event->event_date->translatedFormat('d M Y'))
        ->assertSee('name="event_date"', false)
        ->assertSee($event->event_date->toDateString())
        ->assertDontSee('name="event_date" value="0"', false);
});

test('event validation ignores current event when editing overlap checks', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->toDateString(),
        'start_time' => '15:00',
        'end_time' => '17:00',
    ]);

    $this->actingAs($user)
        ->put(route('admin.events.update', $event), [
            'title' => 'Same Slot Edited',
            'theme' => $event->theme,
            'map_name' => $event->map_name,
            'event_date' => $event->event_date->toDateString(),
            'start_time' => '15:00',
            'end_time' => '17:00',
            'status' => Event::STATUS_PUBLISHED,
        ])
        ->assertRedirect(adminEventDateUrl($event->event_date->toDateString()));

    expect($event->refresh()->title)->toBe('Same Slot Edited');
});

test('admin can update event progress from the dashboard actions', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'creator')->create();

    $this->actingAs($user)
        ->post(route('admin.events.progress', $event), [
            'progress_status' => Event::PROGRESS_DONE,
        ])
        ->assertRedirect();

    expect($event->refresh()->progress_status)->toBe(Event::PROGRESS_DONE);

    $this->actingAs($user)
        ->post(route('admin.events.progress', $event), [
            'progress_status' => Event::PROGRESS_CANCELED,
        ])
        ->assertRedirect();

    expect($event->refresh()->progress_status)->toBe(Event::PROGRESS_CANCELED);

    $this->actingAs($user)
        ->post(route('admin.events.progress', $event), [
            'progress_status' => Event::PROGRESS_SCHEDULED,
        ])
        ->assertRedirect();

    expect($event->refresh()->progress_status)->toBe(Event::PROGRESS_SCHEDULED);
});

test('admin dashboard shows progress action buttons for event rows', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'title' => 'Action Button Event',
        'event_date' => $date,
        'progress_status' => Event::PROGRESS_SCHEDULED,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Action Button Event')
        ->assertSee('Published')
        ->assertSee('Terjadwal')
        ->assertSee('Aksi')
        ->assertSee('Status Event')
        ->assertDontSee('>Kelola Event</p>', false)
        ->assertDontSee('Tersedia')
        ->assertSee('Selesai')
        ->assertSee('Cancel')
        ->assertSee('aria-label="Edit event Action Button Event"', false)
        ->assertSee('aria-label="Hapus event Action Button Event"', false)
        ->assertSee('x-show="deleteOpen"', false)
        ->assertSee('Hapus event?')
        ->assertSee('Batalkan')
        ->assertSee('Hapus Event')
        ->assertDontSee('confirm(', false)
        ->assertSee(route('admin.events.edit', Event::query()->first()), false)
        ->assertSee(route('admin.events.destroy', Event::query()->first()), false)
        ->assertSee(route('admin.events.progress', Event::query()->first()), false);
});

test('admin dashboard keeps completed and canceled event rows neutral', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    Event::factory()->for($user, 'creator')->create([
        'title' => 'Done Tone Event',
        'event_date' => $date,
        'progress_status' => Event::PROGRESS_DONE,
    ]);

    Event::factory()->for($user, 'creator')->create([
        'title' => 'Cancel Tone Event',
        'event_date' => $date,
        'progress_status' => Event::PROGRESS_CANCELED,
        'start_time' => '18:00',
        'end_time' => '19:00',
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Done Tone Event')
        ->assertSee('Cancel Tone Event')
        ->assertDontSee('admin-event-row-done', false)
        ->assertDontSee('admin-event-row-canceled', false)
        ->assertSee('border-t-8 border-zinc-50 bg-white', false);
});

test('admin progress action rejects invalid progress status', function () {
    $user = User::factory()->create();
    $event = Event::factory()->for($user, 'creator')->create();

    $this->actingAs($user)
        ->post(route('admin.events.progress', $event), [
            'progress_status' => 'archived',
        ])
        ->assertSessionHasErrors(['progress_status']);

    expect($event->refresh()->progress_status)->toBe(Event::PROGRESS_SCHEDULED);
});

test('admin dashboard includes top right profile dropdown content', function () {
    $user = User::factory()->create([
        'name' => 'Queenlin Admin',
        'email' => 'admin@queenlin.local',
    ]);

    expect(file_get_contents(resource_path('views/components/layouts/admin.blade.php')))
        ->not->toContain("document.documentElement.classList.remove('dark')")
        ->not->toContain("window.localStorage.setItem('flux.appearance', 'light')");

    $this->actingAs($user)
        ->get(route('admin.events.index'))
        ->assertOk()
        ->assertSee('Queenlin Admin')
        ->assertSee('queenlin-theme-toggle', false)
        ->assertSee('admin@queenlin.local')
        ->assertSeeInOrder(['admin@queenlin.local', 'Settings', 'Logout'])
        ->assertSee(route('settings'), false)
        ->assertSee('Logout');
});

test('admin events page only shows the page level add event action', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('admin.events.index'))
        ->assertOk();

    expect(substr_count($response->getContent(), 'Tambah Event'))->toBe(1);
});

test('admin dashboard shows discord send action for unsent month with published events', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');
    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);

    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);

    Event::factory()->draft()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => $month]))
        ->assertOk()
        ->assertSee('Discord Schedule')
        ->assertSee('Kirim Jadwal ke Discord')
        ->assertSee('Belum dikirim')
        ->assertSee('1 published event')
        ->assertSee('Send to Discord')
        ->assertDontSee('Update Discord');
});

test('sending monthly schedule creates dispatch state and switches dashboard to update', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');
    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'monthly-message-id']),
    ]);

    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.send'), ['month' => $month])
        ->assertRedirect();

    $dispatch = ScheduleDispatch::query()->firstOrFail();

    expect($dispatch->month->toDateString())->toBe(now()->startOfMonth()->toDateString())
        ->and($dispatch->sent_by)->toBe($user->id)
        ->and($dispatch->discord_message_id)->toBe('monthly-message-id')
        ->and($dispatch->discord_message_ids)->toBe(['monthly-message-id'])
        ->and($dispatch->webhook_hash)->toBe(DiscordSetting::webhookHash('https://discord.com/api/webhooks/123/schedule-token'));

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => $month]))
        ->assertOk()
        ->assertSee('Sudah dikirim')
        ->assertSee('Update Discord')
        ->assertSee('Delete Discord')
        ->assertDontSee('Send to Discord');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'wait=true')
        && str_contains((string) data_get($request->data(), 'content'), '# 📅 SCHEDULES')
        && data_get($request->data(), 'embeds') === null);
});

test('schedule sent to a different webhook shows send action again', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/999/new-schedule-token',
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);
    ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'old-monthly-message-id',
        'discord_message_ids' => ['old-monthly-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/123/old-schedule-token'),
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => $month]))
        ->assertOk()
        ->assertSee('Belum dikirim')
        ->assertSee('Webhook schedule berubah. Kirim ulang untuk webhook aktif.')
        ->assertSee('Send to Discord')
        ->assertDontSee('Update Discord')
        ->assertDontSee('Delete Discord');
});

test('sending schedule after webhook change overwrites dispatch with new webhook hash', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/999/new-schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'new-monthly-message-id']),
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);
    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'old-monthly-message-id',
        'discord_message_ids' => ['old-monthly-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/123/old-schedule-token'),
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.send'), ['month' => $month])
        ->assertRedirect();

    expect($dispatch->refresh()->discord_message_id)->toBe('new-monthly-message-id')
        ->and($dispatch->discord_message_ids)->toBe(['new-monthly-message-id'])
        ->and($dispatch->webhook_hash)->toBe(DiscordSetting::webhookHash('https://discord.com/api/webhooks/999/new-schedule-token'));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '999/new-schedule-token'));
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

test('update and delete schedule do not touch discord when webhook changed', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/999/new-schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'unexpected']),
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);
    ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'old-monthly-message-id',
        'discord_message_ids' => ['old-monthly-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/123/old-schedule-token'),
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.update'), ['month' => $month])
        ->assertRedirect()
        ->assertSessionHas('status', 'Schedule bulan ini belum dikirim ke webhook aktif.');

    $this->actingAs($user)
        ->delete(route('admin.schedule-dispatches.destroy'), ['month' => $month])
        ->assertRedirect()
        ->assertSessionHas('status', 'Schedule bulan ini belum dikirim ke webhook aktif.');

    Http::assertNothingSent();
});

test('monthly discord schedule uses requested markdown format', function () {
    $user = User::factory()->create();

    Date::setTestNow(CarbonImmutable::parse('2026-05-02 00:00:00', 'Asia/Jakarta'));

    Event::factory()->for($user, 'creator')->create([
        'event_date' => '2026-05-01',
        'start_time' => '19:00',
        'map_name' => 'RCL DAY 4',
        'theme' => 'Swiss Stage Round 2',
        'prizepool' => '100000000',
        'progress_status' => Event::PROGRESS_SCHEDULED,
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => '2026-05-02',
        'start_time' => '23:30',
        'map_name' => 'MOUNT VOIDE V2',
        'theme' => 'Balap 3 Sesi Umum & Allstar',
        'prizepool' => '1000000',
        'progress_status' => Event::PROGRESS_CANCELED,
    ]);

    $payloads = app(DiscordMessageBuilder::class)->monthlySchedule('2026-05');
    $content = $payloads[0]['content'];

    expect($payloads)->toHaveCount(1)
        ->and($content)->toContain('# 📅 SCHEDULES MEI 2026')
        ->and($content)->toContain('## ✅ 01 MEI 2026')
        ->and($content)->toContain('🕖 **19.00 WIB** ┃ **RCL DAY 4**')
        ->and($content)->toContain('🎉 Swiss Stage Round 2')
        ->and($content)->toContain('💸 Prizepool: **100.000.000 IDR**')
        ->and($content)->toContain('━━━━━━━━━━━━━━━━━━━━')
        ->and($content)->toContain('## 📌 02 MEI 2026')
        ->and($content)->toContain('🕖 **23.30 WIB** ┃ **MOUNT VOIDE V2** ❌');

    Date::setTestNow();
});

test('sending a long monthly schedule stores every split discord message id', function () {
    $user = User::factory()->create();
    $messageIndex = 0;

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => function () use (&$messageIndex) {
            $messageIndex++;

            return Http::response(['id' => 'schedule-message-'.$messageIndex]);
        },
    ]);

    foreach (range(1, 5) as $day) {
        Event::factory()->for($user, 'creator')->create([
            'event_date' => now()->startOfMonth()->addDays($day)->toDateString(),
            'theme' => str_repeat('Tema panjang ', 45),
            'map_name' => 'Map '.$day,
            'status' => Event::STATUS_PUBLISHED,
        ]);
    }

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.send'), ['month' => now()->format('Y-m')])
        ->assertRedirect();

    $dispatch = ScheduleDispatch::query()->firstOrFail();

    expect($dispatch->discord_message_ids)->toHaveCount($messageIndex)
        ->and($messageIndex)->toBeGreaterThan(1);

    Http::assertSentCount($messageIndex);
});

test('updating monthly schedule refreshes existing dispatch without duplicates', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');
    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'monthly-message-id']),
    ]);

    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);

    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'monthly-message-id',
        'sent_at' => now()->subDay(),
        'updated_sent_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.update'), ['month' => $month])
        ->assertRedirect();

    expect(ScheduleDispatch::query()->whereDate('month', now()->startOfMonth()->toDateString())->count())->toBe(1)
        ->and($dispatch->refresh()->updated_sent_at)->not->toBeNull()
        ->and($dispatch->discord_message_ids)->toBe(['monthly-message-id']);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/messages/monthly-message-id'));
});

test('monthly schedule update resets dispatch when discord message was deleted manually', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['message' => 'Unknown Message', 'code' => 10008], 404),
    ]);

    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);

    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'deleted-monthly-message-id',
        'discord_message_ids' => ['deleted-monthly-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/123/schedule-token'),
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.update'), ['month' => $month])
        ->assertRedirect()
        ->assertSessionHas('status', 'Pesan schedule Discord tidak ditemukan lagi. Status lokal direset, silakan Send to Discord ulang.');

    expect($dispatch->fresh())->toBeNull();

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => $month]))
        ->assertOk()
        ->assertSee('Send to Discord')
        ->assertDontSee('Update Discord');
});

test('updating monthly schedule sends extra split messages when needed', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');
    $messageIndex = 1;

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => function ($request) use (&$messageIndex) {
            if ($request->method() === 'POST') {
                $messageIndex++;

                return Http::response(['id' => 'monthly-message-id-'.$messageIndex]);
            }

            return Http::response(['id' => 'monthly-message-id-1']);
        },
    ]);

    foreach (range(1, 5) as $day) {
        Event::factory()->for($user, 'creator')->create([
            'event_date' => now()->startOfMonth()->addDays($day)->toDateString(),
            'theme' => str_repeat('Tema panjang ', 45),
            'map_name' => 'Map '.$day,
            'status' => Event::STATUS_PUBLISHED,
        ]);
    }

    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'monthly-message-id-1',
        'discord_message_ids' => ['monthly-message-id-1'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.update'), ['month' => $month])
        ->assertRedirect();

    expect($dispatch->refresh()->discord_message_ids)->toHaveCount($messageIndex)
        ->and($messageIndex)->toBeGreaterThan(1);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/messages/monthly-message-id-1'));
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'wait=true'));
});

test('updating monthly schedule deletes stale split messages when fewer are needed', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'monthly-message-id-1']),
    ]);

    Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
        'status' => Event::STATUS_PUBLISHED,
    ]);

    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'monthly-message-id-1',
        'discord_message_ids' => ['monthly-message-id-1', 'monthly-message-id-2'],
    ]);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.update'), ['month' => $month])
        ->assertRedirect();

    expect($dispatch->refresh()->discord_message_ids)->toBe(['monthly-message-id-1']);
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/messages/monthly-message-id-2'));
});

test('deleting monthly schedule removes discord message and dispatch state', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');
    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(status: 204),
    ]);

    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => now()->startOfMonth()->toDateString(),
        'discord_message_id' => 'monthly-message-id',
        'discord_message_ids' => ['monthly-message-id', 'monthly-message-id-2'],
    ]);

    $this->actingAs($user)
        ->delete(route('admin.schedule-dispatches.destroy'), ['month' => $month])
        ->assertRedirect();

    expect($dispatch->fresh())->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/messages/monthly-message-id'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/messages/monthly-message-id-2'));
});

test('discord schedule action is disabled when selected month has no published events', function () {
    $user = User::factory()->create();
    $month = now()->format('Y-m');

    Event::factory()->draft()->for($user, 'creator')->create([
        'event_date' => now()->startOfMonth()->addDay()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => $month]))
        ->assertOk()
        ->assertSee('0 published event')
        ->assertSee('Send to Discord')
        ->assertSee('disabled', false);

    $this->actingAs($user)
        ->post(route('admin.schedule-dispatches.send'), ['month' => $month])
        ->assertRedirect();

    expect(ScheduleDispatch::query()->count())->toBe(0);
});

test('event detail discord actions send update and delete a per event message', function () {
    $user = User::factory()->create();
    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'detail-message-id']),
    ]);

    $event = Event::factory()->for($user, 'creator')->create([
        'title' => 'Detail Event',
        'event_date' => now()->toDateString(),
        'description' => 'Ini description detail event untuk Discord.',
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.discord-detail.send', $event))
        ->assertRedirect();

    $dispatch = EventDetailDispatch::query()->firstOrFail();

    expect($dispatch->event_id)->toBe($event->id)
        ->and($dispatch->discord_message_id)->toBe('detail-message-id')
        ->and($dispatch->discord_message_ids)->toBe(['detail-message-id'])
        ->and($dispatch->webhook_hash)->toBe(DiscordSetting::webhookHash('https://discord.com/api/webhooks/456/detail-token'));
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && data_get($request->data(), 'content') === "Ini description detail event untuk Discord.\n\n@everyone"
        && data_get($request->data(), 'allowed_mentions.parse') === ['everyone']
        && data_get($request->data(), 'embeds') === null);

    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'detail-message-id']),
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.discord-detail.update', $event))
        ->assertRedirect();

    expect($dispatch->refresh()->updated_sent_at)->not->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/messages/detail-message-id'));

    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(status: 204),
    ]);

    $this->actingAs($user)
        ->delete(route('admin.events.discord-detail.destroy', $event))
        ->assertRedirect();

    expect($dispatch->fresh())->toBeNull();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/messages/detail-message-id'));
});

test('event detail update resets dispatch when discord message was deleted manually', function () {
    $user = User::factory()->create();

    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['message' => 'Unknown Message', 'code' => 10008], 404),
    ]);

    $event = Event::factory()->for($user, 'creator')->create([
        'event_date' => now()->toDateString(),
        'description' => 'Detail event yang sudah pernah dikirim.',
    ]);
    $dispatch = EventDetailDispatch::query()->create([
        'event_id' => $event->id,
        'discord_message_id' => 'deleted-detail-message-id',
        'discord_message_ids' => ['deleted-detail-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/456/detail-token'),
        'sent_at' => now(),
        'sent_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.discord-detail.update', $event))
        ->assertRedirect()
        ->assertSessionHas('status', 'Pesan detail Discord tidak ditemukan lagi. Status lokal direset, silakan Send Detail ulang.');

    expect($dispatch->fresh())->toBeNull();

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('Send Detail')
        ->assertDontSee('>Update Detail</button>', false);
});

test('event detail discord payload sends poster and keeps everyone mention only at bottom', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs(
        'posters',
        UploadedFile::fake()->image('detail-event.webp', 600, 900),
        'detail-event.webp',
    );

    $event = Event::factory()->create([
        'poster' => 'posters/detail-event.webp',
        'description' => "Halo @everyone\nDetail event.\n@everyone siap-siap.",
    ]);

    $payloads = app(DiscordMessageBuilder::class)->eventDetail($event);

    expect($payloads)->toHaveCount(1)
        ->and($payloads[0]['content'])->toBe("Halo\nDetail event.\nsiap-siap.\n\n@everyone")
        ->and(substr_count($payloads[0]['content'], '@everyone'))->toBe(1)
        ->and(data_get($payloads[0], 'allowed_mentions.parse'))->toBe(['everyone'])
        ->and(data_get($payloads[0], 'embeds'))->toBeNull()
        ->and(data_get($payloads[0], 'attachments.0.filename'))->toBe('detail-event.webp')
        ->and(data_get($payloads[0], 'files.0.name'))->toBe('detail-event.webp')
        ->and(is_file(data_get($payloads[0], 'files.0.path')))->toBeTrue();
});

test('event detail discord send uploads poster as attachment instead of embed', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs(
        'posters',
        UploadedFile::fake()->image('attachment-poster.webp', 600, 900),
        'attachment-poster.webp',
    );

    $user = User::factory()->create();
    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'detail-message-id']),
    ]);

    $event = Event::factory()->for($user, 'creator')->create([
        'poster' => 'posters/attachment-poster.webp',
        'description' => 'Detail event dengan poster.',
    ]);

    $this->actingAs($user)
        ->post(route('admin.events.discord-detail.send', $event))
        ->assertRedirect();

    Http::assertSent(function ($request): bool {
        $payloadJson = collect($request->data())->firstWhere('name', 'payload_json')['contents'] ?? '';
        $payload = json_decode((string) $payloadJson, true);

        return $request->method() === 'POST'
            && $request->isMultipart()
            && $request->hasFile('files[0]')
            && data_get($payload, 'content') === "Detail event dengan poster.\n\n@everyone"
            && data_get($payload, 'embeds') === null
            && data_get($payload, 'attachments.0.filename') === 'attachment-poster.webp';
    });
});

test('event detail sent to a different webhook shows send detail again', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/999/new-detail-token',
    ]);
    $event = Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);
    EventDetailDispatch::query()->create([
        'event_id' => $event->id,
        'discord_message_id' => 'old-detail-message-id',
        'discord_message_ids' => ['old-detail-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/456/old-detail-token'),
        'sent_at' => now(),
        'sent_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m')]))
        ->assertOk()
        ->assertSee('Send Detail')
        ->assertDontSee('>Update Detail</button>', false)
        ->assertDontSee('>Delete Detail</button>', false);
});

test('date level detail state counts only messages sent to active webhook', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();

    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/999/new-detail-token',
    ]);
    $sentToOldWebhook = Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);
    EventDetailDispatch::query()->create([
        'event_id' => $sentToOldWebhook->id,
        'discord_message_id' => 'old-detail-message-id',
        'discord_message_ids' => ['old-detail-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/456/old-detail-token'),
        'sent_at' => now(),
        'sent_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertDontSee('Discord detail tanggal')
        ->assertDontSee('Send Details')
        ->assertDontSee('Update Details')
        ->assertDontSee('Delete Details');
});

test('event detail description is split per event without merging with another event', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();
    $messageIndex = 0;

    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => function () use (&$messageIndex) {
            $messageIndex++;

            return Http::response(['id' => 'detail-message-'.$messageIndex]);
        },
    ]);

    $longEvent = Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'description' => str_repeat('Detail panjang ', 220),
    ]);
    $shortEvent = Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'description' => 'Detail pendek.',
    ]);

    $this->actingAs($user)
        ->post(route('admin.event-detail-dispatches.date.send'), ['event_date' => $date])
        ->assertRedirect();

    $longDispatch = EventDetailDispatch::query()->whereBelongsTo($longEvent)->firstOrFail();
    $shortDispatch = EventDetailDispatch::query()->whereBelongsTo($shortEvent)->firstOrFail();

    expect($longDispatch->discord_message_ids)->toHaveCount(2)
        ->and($shortDispatch->discord_message_ids)->toHaveCount(1);

    Http::assertSentCount(3);
});

test('date level detail action sends one discord message for each published event', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();
    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::sequence()
            ->push(['id' => 'detail-message-1'])
            ->push(['id' => 'detail-message-2']),
    ]);

    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '15:00',
        'end_time' => '16:00',
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '16:00',
        'end_time' => '17:00',
    ]);
    Event::factory()->draft()->for($user, 'creator')->create([
        'event_date' => $date,
        'start_time' => '17:00',
        'end_time' => '18:00',
    ]);

    $this->actingAs($user)
        ->post(route('admin.event-detail-dispatches.date.send'), ['event_date' => $date])
        ->assertRedirect();

    expect(EventDetailDispatch::query()->count())->toBe(2);
    Http::assertSentCount(2);
});

test('dashboard renders event actions below rows in operational and discord boxes', function () {
    $user = User::factory()->create();
    $date = now()->toDateString();
    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
    ]);

    Event::factory()->for($user, 'creator')->create([
        'title' => 'Discord Row Event',
        'event_date' => $date,
    ]);
    $sentEvent = Event::factory()->for($user, 'creator')->create([
        'title' => 'Sent Detail Row Event',
        'event_date' => $date,
    ]);

    EventDetailDispatch::query()->create([
        'event_id' => $sentEvent->id,
        'discord_message_id' => 'detail-message-id',
        'sent_at' => now(),
        'sent_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('admin.events.index', ['month' => now()->format('Y-m'), 'date' => $date]))
        ->assertOk()
        ->assertSee('Aksi')
        ->assertSee('Status Event')
        ->assertDontSee('>Kelola Event</p>', false)
        ->assertDontSee('Operasional')
        ->assertSee('Discord')
        ->assertSee('Selesai')
        ->assertSee('Cancel')
        ->assertSee('Edit')
        ->assertSee('Hapus')
        ->assertSee('aria-label="Edit event Discord Row Event"', false)
        ->assertSee('aria-label="Hapus event Discord Row Event"', false)
        ->assertSee('x-show="deleteOpen"', false)
        ->assertSee('Hapus event?')
        ->assertSee('Batalkan')
        ->assertDontSee('confirm(', false)
        ->assertSee('Send Detail')
        ->assertSee('Update Detail')
        ->assertSee('Delete Detail')
        ->assertDontSee('Discord detail tanggal')
        ->assertDontSee('Send Details');
});

test('monthly auto command only dispatches on day one when enabled', function () {
    $user = User::factory()->create();
    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/123/schedule-token',
        'auto_schedule_enabled' => true,
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => '2026-05-01',
    ]);

    Date::setTestNow(CarbonImmutable::parse('2026-05-02 00:00:00', 'Asia/Jakarta'));
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'monthly-message-id']),
    ]);

    $this->artisan('discord:dispatch-monthly-schedules')
        ->assertExitCode(0);

    expect(ScheduleDispatch::query()->count())->toBe(0);
    Http::assertNothingSent();

    Date::setTestNow(CarbonImmutable::parse('2026-05-01 00:00:00', 'Asia/Jakarta'));

    $this->artisan('discord:dispatch-monthly-schedules')
        ->assertExitCode(0);

    expect(ScheduleDispatch::query()->first()?->discord_message_id)->toBe('monthly-message-id');
    Date::setTestNow();
});

test('monthly auto command sends a new schedule when webhook changed', function () {
    $user = User::factory()->create();

    DiscordSetting::current()->update([
        'schedule_webhook_url' => 'https://discord.com/api/webhooks/999/new-schedule-token',
        'auto_schedule_enabled' => true,
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => '2026-05-01',
    ]);
    $dispatch = ScheduleDispatch::factory()->for($user, 'sender')->create([
        'month' => '2026-05-01',
        'discord_message_id' => 'old-monthly-message-id',
        'discord_message_ids' => ['old-monthly-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/123/old-schedule-token'),
    ]);

    Date::setTestNow(CarbonImmutable::parse('2026-05-01 00:00:00', 'Asia/Jakarta'));
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'new-monthly-message-id']),
    ]);

    $this->artisan('discord:dispatch-monthly-schedules')
        ->assertExitCode(0);

    expect($dispatch->refresh()->discord_message_id)->toBe('new-monthly-message-id')
        ->and($dispatch->webhook_hash)->toBe(DiscordSetting::webhookHash('https://discord.com/api/webhooks/999/new-schedule-token'));
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '999/new-schedule-token'));

    Date::setTestNow();
});

test('daily auto detail command dispatches event details when enabled', function () {
    $user = User::factory()->create();
    $date = '2026-05-08';
    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/456/detail-token',
        'auto_detail_enabled' => true,
    ]);
    Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'detail-message-id']),
    ]);

    $this->artisan('discord:dispatch-event-details', ['--date' => $date])
        ->assertExitCode(0);

    expect(EventDetailDispatch::query()->first()?->discord_message_id)->toBe('detail-message-id');
});

test('daily auto detail command sends a new detail message when webhook changed', function () {
    $user = User::factory()->create();
    $date = '2026-05-08';

    DiscordSetting::current()->update([
        'detail_webhook_url' => 'https://discord.com/api/webhooks/999/new-detail-token',
        'auto_detail_enabled' => true,
    ]);
    $event = Event::factory()->for($user, 'creator')->create([
        'event_date' => $date,
    ]);
    $dispatch = EventDetailDispatch::query()->create([
        'event_id' => $event->id,
        'discord_message_id' => 'old-detail-message-id',
        'discord_message_ids' => ['old-detail-message-id'],
        'webhook_hash' => DiscordSetting::webhookHash('https://discord.com/api/webhooks/456/old-detail-token'),
        'sent_at' => now(),
        'sent_by' => $user->id,
    ]);
    Http::fake([
        'https://discord.com/api/webhooks/*' => Http::response(['id' => 'new-detail-message-id']),
    ]);

    $this->artisan('discord:dispatch-event-details', ['--date' => $date])
        ->assertExitCode(0);

    expect($dispatch->refresh()->discord_message_id)->toBe('new-detail-message-id')
        ->and($dispatch->webhook_hash)->toBe(DiscordSetting::webhookHash('https://discord.com/api/webhooks/999/new-detail-token'));
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '999/new-detail-token'));
});
