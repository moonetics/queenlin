<x-layouts.admin :title="__('Kelola Event')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-2xl font-semibold text-zinc-950 dark:text-zinc-50">Event Schedule</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Tambah, edit, publish, dan hapus jadwal shoutcasting Queenlin.</p>
            </div>

            <a href="{{ route('admin.events.create') }}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Tambah Event
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        @php($selectedAdminMonthOption = collect($adminMonthOptions)->firstWhere('value', $month))
        @php($monthlyScheduleSent = $scheduleDispatch?->isForWebhook($discordSettings->schedule_webhook_url) ?? false)
        @php($scheduleWebhookChanged = filled($scheduleDispatch?->discord_message_id) && ! $monthlyScheduleSent && $discordSettings->hasScheduleWebhook())

        <form
            method="GET"
            action="{{ route('admin.events.index') }}"
            x-data="{
                monthOptions: @js($adminMonthOptions),
                dateMonths: @js($adminDateMonths),
                selectedMonth: @js($month),
                selectedDate: @js($date),
                monthOpen: false,
                dateOpen: false,
                navigating: false,
                adminUrl: @js(route('admin.events.index')),
                monthDates() {
                    return this.dateMonths[this.selectedMonth]?.dates || {};
                },
                dateEntries() {
                    return Object.entries(this.monthDates());
                },
                monthButtonLabel() {
                    return this.monthOptions.find((month) => month.value === this.selectedMonth)?.label || 'Pilih bulan';
                },
                monthSummary() {
                    const selectedOption = this.monthOptions.find((month) => month.value === this.selectedMonth);
                    const eventCount = selectedOption?.eventCount ?? Object.values(this.monthDates()).reduce((total, slot) => total + slot.eventCount, 0);
                    const dayCount = Object.keys(this.monthDates()).length;

                    if (dayCount === 0) {
                        return `${eventCount} event`;
                    }

                    return `${dayCount} tanggal · ${eventCount} event`;
                },
                targetUrl(month, date = '') {
                    const params = new URLSearchParams({ month });

                    if (date) {
                        params.set('date', date);
                    }

                    return `${this.adminUrl}?${params.toString()}`;
                },
                visitSelection(month, date = '') {
                    const target = new URL(this.targetUrl(month, date), window.location.origin);

                    this.monthOpen = false;
                    this.dateOpen = false;

                    if (target.href === window.location.href) {
                        return;
                    }

                    if (this.navigating) {
                        return;
                    }

                    this.navigating = true;
                    window.location.assign(target.href);
                },
                slotToneClasses(slot) {
                    if (slot.manualOff) {
                        return 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-400/50 dark:bg-sky-500/15 dark:text-sky-100';
                    }

                    if (slot.disabled || slot.manualFull) {
                        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-400/50 dark:bg-red-500/15 dark:text-red-100';
                    }

                    if (slot.usedMinutes > 240) {
                        return 'border-orange-200 bg-orange-50 text-orange-800 hover:bg-orange-100 dark:border-orange-300/60 dark:bg-orange-500/15 dark:text-orange-100 dark:hover:bg-orange-500/20';
                    }

                    return 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-400/50 dark:bg-emerald-500/15 dark:text-emerald-100 dark:hover:bg-emerald-500/20';
                },
                selectedSlot() {
                    return this.selectedDate ? (this.monthDates()[this.selectedDate] || null) : null;
                },
                dateButtonLabel() {
                    return this.selectedDate && this.selectedSlot() ? this.selectedSlot().label : 'Pilih tanggal';
                },
                slotSummary() {
                    const slot = this.selectedSlot();

                    if (! slot) {
                        return 'Pilih tanggal dulu';
                    }

                    return slot.statusLabel + (slot.eventCount > 0 ? ` · ${slot.eventCount} event` : ' · 0 event');
                },
                chooseDate(date) {
                    this.selectedDate = date;
                    this.visitSelection(this.selectedMonth, date);
                },
                chooseMonth(month) {
                    this.selectedMonth = month;
                    this.selectedDate = '';
                    this.visitSelection(month);
                },
            }"
            x-ref="filterForm"
            class="grid gap-3 rounded-lg border border-emerald-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:grid-cols-2"
        >
            <div class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                <span>Bulan</span>
                <input type="hidden" name="month" value="{{ $month }}" :value="selectedMonth">
                <div class="relative admin-month-dropdown" @click.outside="monthOpen = false">
                    <button type="button" class="flex min-h-[52px] w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:ring-emerald-500/25" @click="monthOpen = ! monthOpen">
                        <span>
                            <span class="block font-semibold text-zinc-900 dark:text-zinc-50" x-text="monthButtonLabel()">{{ $selectedAdminMonthOption['label'] ?? $month }}</span>
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400" x-text="monthSummary()">{{ count($adminDateMonths[$month]['dates'] ?? []) }} tanggal · {{ $selectedAdminMonthOption['eventCount'] ?? 0 }} event</span>
                        </span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Pilih</span>
                    </button>

                    <div x-cloak x-show="monthOpen" x-transition class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                        @foreach ($adminMonthOptions as $monthOption)
                            <button
                                type="button"
                                @click="chooseMonth(@js($monthOption['value']))"
                                :disabled="navigating"
                                class="mb-1 flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm font-semibold text-zinc-800 hover:bg-emerald-50 disabled:cursor-wait disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-emerald-500/15"
                                :class="selectedMonth === @js($monthOption['value']) ? 'border-emerald-500 bg-emerald-100 text-emerald-950 ring-2 ring-emerald-500 ring-offset-2 dark:border-emerald-300 dark:bg-emerald-500/20 dark:text-emerald-50 dark:ring-emerald-300 dark:ring-offset-zinc-800' : ''"
                            >
                                <span>
                                    <span class="block">{{ $monthOption['label'] }}</span>
                                    <span class="mt-0.5 block text-[11px] font-medium text-zinc-500 dark:text-zinc-400">{{ $monthOption['eventCount'] }} event</span>
                                </span>
                                <span x-show="selectedMonth === @js($monthOption['value'])" class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">Dipilih</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                <span>Tanggal</span>
                <input type="hidden" name="date" value="{{ $date }}" :value="selectedDate">
                <div class="relative admin-date-dropdown" @click.outside="dateOpen = false">
                    <button type="button" class="flex min-h-[52px] w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:ring-emerald-500/25" @click="dateOpen = ! dateOpen">
                        <span>
                            <span class="block font-semibold text-zinc-900 dark:text-zinc-50" x-text="dateButtonLabel()"></span>
                            <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400" x-text="slotSummary()"></span>
                        </span>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Pilih</span>
                    </button>

                    <div x-cloak x-show="dateOpen" x-transition class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                        <template x-for="[dateValue, slot] in dateEntries()" :key="dateValue">
                            <button
                                type="button"
                                @click="chooseDate(dateValue)"
                                :disabled="navigating"
                                class="mb-1 w-full rounded-lg border px-3 py-2 text-left text-xs font-semibold disabled:cursor-wait disabled:opacity-60"
                                :class="[
                                    slotToneClasses(slot),
                                    selectedDate === dateValue ? 'border-emerald-500 bg-emerald-200 text-emerald-950 ring-2 ring-emerald-500 ring-offset-2 dark:border-emerald-300 dark:bg-emerald-500/25 dark:text-emerald-50 dark:ring-emerald-300 dark:ring-offset-zinc-800' : ''
                                ]"
                            >
                                <span class="flex items-start justify-between gap-2">
                                    <span>
                                        <span class="block text-sm" x-text="slot.label"></span>
                                        <span class="mt-0.5 block text-[11px] font-medium" x-text="slot.statusLabel"></span>
                                    </span>
                                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-bold ring-1 ring-emerald-100 dark:bg-zinc-900/80 dark:ring-zinc-700" x-text="slot.eventCount + ' event'"></span>
                                </span>
                                <span x-show="selectedDate === dateValue" class="mt-1 inline-flex rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">Dipilih</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </form>

	        <div class="rounded-lg border border-emerald-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
	            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
	                <div>
	                    <div class="flex flex-wrap items-center gap-2">
	                    <p class="text-xs font-semibold uppercase tracking-normal text-emerald-700 dark:text-emerald-300">Status Tanggal</p>
	                        @if ($date)
	                            <span class="{{ $manualDateStatus === \App\Models\ManualFullDate::STATUS_OFF ? 'bg-sky-100 text-sky-800 ring-sky-200' : ($manualDateStatus === \App\Models\ManualFullDate::STATUS_FULL ? 'bg-red-100 text-red-800 ring-red-200' : 'bg-emerald-100 text-emerald-800 ring-emerald-200') }} rounded-full px-2.5 py-1 text-xs font-bold ring-1">
	                                {{ $manualDateStatusLabel }}
	                            </span>
	                        @endif
	                    </div>

	                    @if ($date)
	                        <h3 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-zinc-50">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('d F Y') }}</h3>
	                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
	                            <span class="font-semibold text-zinc-950 dark:text-zinc-50">{{ $selectedDateEventCount }} event</span> ada pada tanggal ini. Atur status penuh/libur dari sini.
	                            @if ($selectedDateSlot)
	                                <span class="font-semibold text-zinc-950 dark:text-zinc-50">Slot tanggal: {{ $selectedDateSlot['statusLabel'] }}.</span>
	                            @endif
	                        </p>
	                    @else
	                        <h3 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-zinc-50">Pilih tanggal dulu</h3>
	                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Pilih tanggal dulu untuk mengatur Penuh atau Libur.</p>
	                    @endif
	                </div>

	                @if ($date)
	                    <div class="flex flex-wrap gap-2 lg:self-center lg:justify-end">
	                        <form method="POST" action="{{ route('admin.manual-full-dates.toggle') }}">
	                            @csrf
	                            <input type="hidden" name="month" value="{{ $month }}">
	                            <input type="hidden" name="event_date" value="{{ $date }}">
	                            <input type="hidden" name="status" value="{{ \App\Models\ManualFullDate::STATUS_FULL }}">
	                            <button class="{{ $manualDateStatus === \App\Models\ManualFullDate::STATUS_FULL ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-red-50 text-red-700 ring-1 ring-red-200 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-100 dark:ring-red-400/30 dark:hover:bg-red-500/20' }} rounded-lg px-4 py-2 text-sm font-semibold shadow-sm">
	                                {{ $manualDateStatus === \App\Models\ManualFullDate::STATUS_FULL ? 'Buka Penuh' : 'Tandai Penuh' }}
	                            </button>
	                        </form>

	                        <form method="POST" action="{{ route('admin.manual-full-dates.toggle') }}">
	                            @csrf
	                            <input type="hidden" name="month" value="{{ $month }}">
	                            <input type="hidden" name="event_date" value="{{ $date }}">
	                            <input type="hidden" name="status" value="{{ \App\Models\ManualFullDate::STATUS_OFF }}">
	                            <button class="{{ $manualDateStatus === \App\Models\ManualFullDate::STATUS_OFF ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 hover:bg-sky-100 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-400/30 dark:hover:bg-sky-500/20' }} rounded-lg px-4 py-2 text-sm font-semibold shadow-sm">
	                                {{ $manualDateStatus === \App\Models\ManualFullDate::STATUS_OFF ? 'Buka Libur' : 'Tandai Libur' }}
	                            </button>
	                        </form>
	                    </div>
	                @endif
	            </div>
	        </div>

        <div class="grid gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 shadow-sm shadow-emerald-100 dark:border-emerald-400/40 dark:bg-emerald-950/40 dark:shadow-none sm:grid-cols-[1fr_auto] sm:items-center">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-semibold uppercase tracking-normal text-emerald-700 dark:text-emerald-300">Discord Schedule</p>
                    <span class="{{ $monthlyScheduleSent ? 'bg-emerald-600 text-white' : 'bg-white text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-400/30' }} rounded-full px-2.5 py-1 text-xs font-bold">
                        {{ $monthlyScheduleSent ? 'Sudah dikirim' : 'Belum dikirim' }}
                    </span>
                    <span class="{{ $discordSettings->auto_schedule_enabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-100 dark:text-emerald-900' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }} rounded-full px-2.5 py-1 text-xs font-bold">
                        Auto {{ $discordSettings->auto_schedule_enabled ? 'On' : 'Off' }}
                    </span>
                </div>
                <h3 class="mt-2 text-xl font-semibold text-zinc-950 dark:text-zinc-50">Kirim Jadwal ke Discord</h3>
                <p class="mt-1 text-sm font-medium text-emerald-900 dark:text-emerald-300">{{ $schedulePayload['title'] }}</p>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <span class="font-semibold text-zinc-950 dark:text-zinc-50">{{ $schedulePayload['event_count'] }} published event</span> siap masuk jadwal Discord.
                    @if (! $discordSettings->hasScheduleWebhook())
                        Webhook schedule belum diatur di Settings Discord.
                    @elseif ($monthlyScheduleSent)
                        Terakhir dikirim {{ $scheduleDispatch->sent_at?->translatedFormat('d M Y H:i') }}.
                        @if ($scheduleDispatch->updated_sent_at)
                            Update terakhir {{ $scheduleDispatch->updated_sent_at->translatedFormat('d M Y H:i') }}.
                        @endif
                    @elseif ($scheduleWebhookChanged)
                        Webhook schedule berubah. Kirim ulang untuk webhook aktif.
                    @elseif ($scheduleDispatch)
                        Data lama ditemukan tanpa message id Discord. Kirim ulang untuk mengaktifkan update/delete.
                    @else
                        Belum pernah dikirim untuk bulan ini.
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap gap-2 sm:justify-end">
                @if ($monthlyScheduleSent)
                    <form method="POST" action="{{ route('admin.schedule-dispatches.update') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button
                            @disabled($schedulePayload['event_count'] === 0 || ! $discordSettings->hasScheduleWebhook())
                            class="{{ $schedulePayload['event_count'] === 0 || ! $discordSettings->hasScheduleWebhook() ? 'cursor-not-allowed bg-white text-zinc-400 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-700' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} rounded-lg px-4 py-2 text-sm font-bold shadow-sm"
                        >Update Discord</button>
                    </form>

                    <form method="POST" action="{{ route('admin.schedule-dispatches.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button
                            @disabled(! $discordSettings->hasScheduleWebhook())
                            class="{{ ! $discordSettings->hasScheduleWebhook() ? 'cursor-not-allowed bg-white text-zinc-400 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-700' : 'bg-red-50 text-red-700 ring-1 ring-red-200 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-100 dark:ring-red-400/30 dark:hover:bg-red-500/20' }} rounded-lg px-4 py-2 text-sm font-bold shadow-sm"
                        >Delete Discord</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.schedule-dispatches.send') }}">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month }}">
                        <button
                            @disabled($schedulePayload['event_count'] === 0 || ! $discordSettings->hasScheduleWebhook())
                            class="{{ $schedulePayload['event_count'] === 0 || ! $discordSettings->hasScheduleWebhook() ? 'cursor-not-allowed bg-white text-zinc-400 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-700' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} rounded-lg px-4 py-2 text-sm font-bold shadow-sm"
                        >Send to Discord</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-emerald-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[840px] text-left text-sm">
                    <thead class="bg-emerald-50 text-xs font-semibold uppercase tracking-normal text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <tr>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Tanggal</th>
	                            <th class="px-4 py-3">Jam</th>
	                            <th class="px-4 py-3">Map</th>
	                            <th class="px-4 py-3">Status</th>
	                            <th class="px-4 py-3">Aksi</th>
	                        </tr>
                    </thead>
	                    <tbody>
		                        @forelse ($events as $event)
		                            <tr class="border-t-8 border-zinc-50 bg-white first:border-t-0 dark:border-zinc-950 dark:bg-zinc-900">
	                                <td class="px-4 pb-3 pt-5">
	                                    <div class="flex items-center gap-3">
	                                        <div class="size-12 overflow-hidden rounded-lg bg-emerald-50 dark:bg-zinc-800">
                                            @if ($event->poster_url)
                                                <img src="{{ $event->poster_url }}" alt="" class="size-full object-cover">
                                            @endif
                                        </div>
                                        <div>
                                            <div class="font-semibold text-zinc-950 dark:text-zinc-50">{{ $event->title }}</div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $event->theme }}</div>
                                        </div>
                                    </div>
	                                </td>
	                                <td class="px-4 pb-3 pt-5 text-zinc-700 dark:text-zinc-300">
	                                    <div>{{ $event->event_date->format('d M Y') }}</div>
	                                </td>
	                                <td class="px-4 pb-3 pt-5 text-zinc-700 dark:text-zinc-300">{{ $event->start_time->format('H:i') }}-{{ $event->end_time->format('H:i') }}</td>
	                                <td class="px-4 pb-3 pt-5 text-zinc-700 dark:text-zinc-300">{{ $event->map_name }}</td>
                                <td class="px-4 pb-3 pt-5">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $event->status === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-100 dark:text-emerald-900' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ ucfirst($event->status) }}</span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $event->progress_status === \App\Models\Event::PROGRESS_DONE ? 'bg-emerald-600 text-white' : ($event->progress_status === \App\Models\Event::PROGRESS_CANCELED ? 'bg-red-100 text-red-800 dark:bg-red-500/20 dark:text-red-100' : 'bg-sky-100 text-sky-800 dark:bg-sky-100 dark:text-sky-900') }}">{{ $event->progressLabel() }}</span>
	                                    </div>
		                                </td>
	                                <td class="px-4 pb-3 pt-5">
	                                    <div class="flex items-center gap-2">
		                                        <a
		                                            href="{{ route('admin.events.edit', $event) }}"
		                                            aria-label="Edit event {{ $event->title }}"
		                                            title="Edit event"
		                                            class="inline-flex size-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/40 dark:hover:bg-emerald-500/20 dark:focus:ring-offset-zinc-900"
		                                        >
		                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
		                                                <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
		                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
		                                            </svg>
		                                            <span class="sr-only">Edit</span>
		                                        </a>
		                                        <div x-data="{ deleteOpen: false }" @keydown.escape.window="deleteOpen = false">
		                                            <button
		                                                type="button"
		                                                aria-label="Hapus event {{ $event->title }}"
		                                                title="Hapus event"
		                                                @click="deleteOpen = true"
		                                                class="inline-flex size-9 items-center justify-center rounded-lg bg-red-600 text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-600 dark:hover:bg-red-500 dark:focus:ring-offset-zinc-900"
		                                            >
		                                                <svg class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
	                                                    <path d="M3 6h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
	                                                    <path d="M8 6V4h8v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
	                                                    <path d="M19 6l-1 14H6L5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
	                                                    <path d="M10 11v5M14 11v5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
		                                                </svg>
		                                                <span class="sr-only">Hapus</span>
		                                            </button>

		                                            <div
		                                                x-cloak
		                                                x-show="deleteOpen"
		                                                x-transition.opacity
		                                                class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/70 p-4"
		                                                role="dialog"
		                                                aria-modal="true"
		                                                aria-labelledby="delete-event-title-{{ $event->id }}"
		                                            >
		                                                <div class="absolute inset-0" @click="deleteOpen = false"></div>
		                                                <div class="relative w-full max-w-md rounded-lg bg-white p-5 text-left shadow-xl ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700">
		                                                    <div class="flex items-start gap-3">
		                                                        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-200">
		                                                            <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
		                                                                <path d="M3 6h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
		                                                                <path d="M8 6V4h8v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
		                                                                <path d="M19 6l-1 14H6L5 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
		                                                            </svg>
		                                                        </div>
		                                                        <div class="min-w-0">
		                                                            <h3 id="delete-event-title-{{ $event->id }}" class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Hapus event?</h3>
		                                                            <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
		                                                                Event <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $event->title }}</span> akan dihapus dari daftar admin. Aksi ini tidak bisa dibatalkan.
		                                                            </p>
		                                                        </div>
		                                                    </div>

		                                                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
		                                                        <button
		                                                            type="button"
		                                                            class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-400 focus:ring-offset-2 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-700 dark:focus:ring-offset-zinc-900"
		                                                            @click="deleteOpen = false"
		                                                        >
		                                                            Batalkan
		                                                        </button>
		                                                        <form method="POST" action="{{ route('admin.events.destroy', $event) }}">
		                                                            @csrf
		                                                            @method('DELETE')
		                                                            <button
		                                                                type="submit"
		                                                                class="w-full rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:w-auto"
		                                                            >
		                                                                Hapus Event
		                                                            </button>
		                                                        </form>
		                                                    </div>
		                                                </div>
		                                            </div>
		                                        </div>
		                                    </div>
		                                </td>
		                            </tr>
		                            <tr class="border-b border-zinc-200 bg-white shadow-[0_10px_18px_-18px_rgba(24,24,27,0.45)] dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-none">
		                                <td colspan="6" class="px-4 pb-6 pt-0">
		                                    <div class="grid gap-3 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-800/60 lg:grid-cols-2">
	                                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 shadow-[inset_3px_0_0_0_#10b981] dark:border-emerald-400/40 dark:bg-emerald-500/10">
	                                            <p class="mb-3 text-[10px] font-bold uppercase tracking-normal text-emerald-700 dark:text-emerald-300">Status Event</p>
	                                            <div class="flex flex-wrap gap-2">
	                                                <form method="POST" action="{{ route('admin.events.progress', $event) }}">
	                                                    @csrf
                                                    <input type="hidden" name="progress_status" value="{{ $event->progress_status === \App\Models\Event::PROGRESS_DONE ? \App\Models\Event::PROGRESS_SCHEDULED : \App\Models\Event::PROGRESS_DONE }}">
                                                    <button class="{{ $event->progress_status === \App\Models\Event::PROGRESS_DONE ? 'bg-white text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50 dark:bg-zinc-900 dark:text-emerald-200 dark:ring-emerald-400/30 dark:hover:bg-emerald-500/10' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} rounded-lg px-3 py-2 text-xs font-semibold">
                                                        {{ $event->progress_status === \App\Models\Event::PROGRESS_DONE ? 'Batal Selesai' : 'Selesai' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.events.progress', $event) }}">
                                                    @csrf
                                                    <input type="hidden" name="progress_status" value="{{ $event->progress_status === \App\Models\Event::PROGRESS_CANCELED ? \App\Models\Event::PROGRESS_SCHEDULED : \App\Models\Event::PROGRESS_CANCELED }}">
                                                    <button class="{{ $event->progress_status === \App\Models\Event::PROGRESS_CANCELED ? 'bg-white text-red-700 ring-1 ring-red-200 hover:bg-red-50 dark:bg-zinc-900 dark:text-red-200 dark:ring-red-400/30 dark:hover:bg-red-500/10' : 'bg-red-50 text-red-700 ring-1 ring-red-100 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-100 dark:ring-red-400/30 dark:hover:bg-red-500/20' }} rounded-lg px-3 py-2 text-xs font-semibold">
                                                        {{ $event->progress_status === \App\Models\Event::PROGRESS_CANCELED ? 'Batal Cancel' : 'Cancel' }}
                                                    </button>
                                                </form>
	                                            </div>
	                                        </div>

	                                        @php($detailMessageSent = $event->detailDispatch?->isForWebhook($discordSettings->detail_webhook_url) ?? false)
	                                        @php($detailDisabled = ! $discordSettings->hasDetailWebhook() || $event->status !== \App\Models\Event::STATUS_PUBLISHED)
                                        <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 shadow-[inset_3px_0_0_0_#5865f2] dark:border-indigo-300/50 dark:bg-indigo-500/10">
                                            <p class="mb-3 text-[10px] font-bold uppercase tracking-normal text-indigo-700 dark:text-indigo-300">Discord</p>
                                            <div class="flex flex-wrap gap-2">
                                                @if ($detailMessageSent)
                                                    <form method="POST" action="{{ route('admin.events.discord-detail.update', $event) }}">
                                                        @csrf
                                                        <button
                                                            @disabled($detailDisabled)
                                                            class="{{ $detailDisabled ? 'cursor-not-allowed bg-white text-zinc-400 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-700' : 'bg-white text-indigo-700 ring-1 ring-indigo-200 hover:bg-indigo-50 dark:bg-zinc-900 dark:text-indigo-200 dark:ring-indigo-300/40 dark:hover:bg-indigo-500/10' }} rounded-lg px-3 py-2 text-xs font-semibold"
                                                        >Update Detail</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.events.discord-detail.destroy', $event) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            @disabled(! $discordSettings->hasDetailWebhook())
                                                            class="{{ ! $discordSettings->hasDetailWebhook() ? 'cursor-not-allowed bg-white text-zinc-400 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-700' : 'bg-red-50 text-red-700 ring-1 ring-red-200 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-100 dark:ring-red-400/30 dark:hover:bg-red-500/20' }} rounded-lg px-3 py-2 text-xs font-semibold"
                                                        >Delete Detail</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('admin.events.discord-detail.send', $event) }}">
                                                        @csrf
                                                        <button
                                                            @disabled($detailDisabled)
                                                            class="{{ $detailDisabled ? 'cursor-not-allowed bg-white text-zinc-400 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:text-zinc-500 dark:ring-zinc-700' : 'bg-[#5865f2] text-white hover:bg-[#4752c4]' }} rounded-lg px-3 py-2 text-xs font-semibold shadow-sm"
                                                        >Send Detail</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
	                                <td colspan="6" class="px-4 py-14 text-center">
                                    <div class="mx-auto max-w-sm">
                                        <p class="font-semibold text-zinc-950 dark:text-zinc-50">No events yet</p>
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Mulai tambahkan jadwal shoutcasting pertama Queenlin.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                {{ $events->links() }}
            </div>
        </div>
    </div>
</x-layouts.admin>
