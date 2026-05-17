<x-layouts.admin :title="$event->exists ? __('Edit Event') : __('Tambah Event')">
    <div class="mx-auto w-full max-w-4xl">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ $event->exists ? 'Edit Event' : 'Tambah Event' }}</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Kelola detail event untuk jadwal shoutcasting Queenlin.</p>
            </div>
            <a href="{{ route('admin.events.index') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Kembali</a>
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-400/40 dark:bg-red-500/10 dark:text-red-100">
                <p class="font-semibold">Ada input yang perlu dicek lagi.</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div
            x-data="{
                dateMonths: @js($dateMonths),
                timeOptions: @js($timeOptions),
                initialDate: @js($selectedDate),
                canKeepInitialDate: @js($canKeepInitialDate),
                selectedMonth: @js($selectedMonth),
                selectedDate: @js($selectedDate),
                start: @js(old('start_time', $event->start_time ? $event->start_time->format('H:i') : '15:00')),
                end: @js(old('end_time', $event->end_time ? $event->end_time->format('H:i') : '00:00')),
                dateOpen: false,
                startOpen: false,
                endOpen: false,
                operationalStart: 900,
                operationalEnd: 1440,
                minBookingMinutes: 5,
                labelForMinutes(minutes) {
                    const hours = Math.floor(minutes / 60);
                    const rest = minutes % 60;
                    return `${hours}j ${rest}m`;
                },
                minutesFromTime(time) {
                    if (! time) {
                        return null;
                    }

                    const [hour, minute] = time.split(':').map(Number);
                    const total = (hour * 60) + minute;

                    return total === 0 ? this.operationalEnd : total;
                },
                monthDates() {
                    return this.dateMonths[this.selectedMonth]?.dates || {};
                },
                dateEntries() {
                    return Object.entries(this.monthDates());
                },
                changeMonth() {
                    if (! (this.canKeepInitialDate && this.initialDate.startsWith(this.selectedMonth))) {
                        this.selectedDate = '';
                    }

                    this.syncTimesForSelectedDate();
                },
                canPickDate(date, slot) {
                    return ! slot.disabled || (this.canKeepInitialDate && date === this.initialDate);
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
                chooseDate(date, slot) {
                    if (! this.canPickDate(date, slot)) {
                        return;
                    }

                    this.selectedDate = date;
                    this.dateOpen = false;
                    this.syncTimesForSelectedDate();
                },
                syncTimesForSelectedDate() {
                    if (! this.selectedDate || ! this.canUseSelectedSlot()) {
                        return;
                    }

                    if (! this.canSelectStart(this.start)) {
                        this.start = this.firstValidStart();
                    }

                    if (! this.canSelectEnd(this.end)) {
                        this.end = this.firstValidEnd();
                    }
                },
                selectedSlot() {
                    return this.selectedDate ? (this.monthDates()[this.selectedDate] || null) : null;
                },
                occupiedRanges() {
                    return this.selectedSlot()?.occupiedRanges || [];
                },
                canUseSelectedSlot() {
                    const slot = this.selectedSlot();

                    return !! slot && (! slot.disabled || (this.canKeepInitialDate && this.selectedDate === this.initialDate));
                },
                rangeOverlaps(start, end) {
                    if (start === null || end === null || end <= start) {
                        return false;
                    }

                    return this.occupiedRanges().some((range) => {
                        const rangeStart = this.minutesFromTime(range.start);
                        const rangeEnd = this.minutesFromTime(range.end);

                        return start < rangeEnd && rangeStart < end;
                    });
                },
                canSelectEnd(value) {
                    const start = this.minutesFromTime(this.start);
                    const end = this.minutesFromTime(value);

                    return start !== null
                        && end !== null
                        && end - start >= this.minBookingMinutes
                        && this.canUseSelectedSlot()
                        && ! this.rangeOverlaps(start, end);
                },
                firstValidStart() {
                    return this.timeOptions.find((option) => this.canSelectStart(option.value))?.value || '';
                },
                firstValidEnd() {
                    return this.timeOptions.find((option) => this.canSelectEnd(option.value))?.value || '';
                },
                canSelectStart(value) {
                    const start = this.minutesFromTime(value);

                    if (! this.selectedDate || ! this.canUseSelectedSlot() || start === null || start < this.operationalStart || start >= this.operationalEnd) {
                        return false;
                    }

                    return this.timeOptions.some((option) => {
                        const end = this.minutesFromTime(option.value);

                        return end - start >= this.minBookingMinutes && ! this.rangeOverlaps(start, end);
                    });
                },
                chooseStart(value) {
                    if (! this.canSelectStart(value)) {
                        return;
                    }

                    this.start = value;

                    if (! this.canSelectEnd(this.end)) {
                        this.end = this.firstValidEnd();
                    }
                },
                chooseEnd(value) {
                    if (this.canSelectEnd(value)) {
                        this.end = value;
                    }
                },
                selectedRangeInvalid() {
                    return ! this.selectedDate || ! this.canUseSelectedSlot() || ! this.canSelectStart(this.start) || ! this.canSelectEnd(this.end);
                },
                guardSubmit(event) {
                    this.$refs.eventDateInput.value = this.selectedDate || '';
                    this.$refs.startTimeInput.value = this.start || '';
                    this.$refs.endTimeInput.value = this.end || '';

                    if (this.selectedRangeInvalid()) {
                        event.preventDefault();
                    }
                },
                dateButtonLabel() {
                    return this.selectedDate && this.selectedSlot() ? this.selectedSlot().label : 'Pilih tanggal';
                },
                slotSummary() {
                    const slot = this.selectedSlot();

                    if (! slot) {
                        return 'Pilih tanggal dulu';
                    }

                    if (slot.manualFull) {
                        return 'Penuh manual';
                    }

                    if (slot.manualOff) {
                        return 'Libur';
                    }

                    if (slot.disabled) {
                        return 'Penuh';
                    }

                    return `Sisa ${this.labelForMinutes(slot.remainingMinutes)}`;
                },
            }"
        >
            <form
                method="POST"
                action="{{ $action }}"
                enctype="multipart/form-data"
                class="grid gap-5 rounded-lg border border-emerald-100 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                @submit="guardSubmit($event)"
            >
                @csrf
                @if ($method !== 'POST')
                    @method($method)
                @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    Judul event
                    <input name="title" value="{{ old('title', $event->title) }}" required class="rounded-lg border border-zinc-200 px-3 py-2 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-emerald-500/25">
                </label>

                <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    Status publikasi
                    <select name="status" required class="rounded-lg border border-zinc-200 px-3 py-2 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-emerald-500/25">
                        <option value="published" @selected(old('status', $event->status) === 'published')>Published</option>
                        <option value="draft" @selected(old('status', $event->status) === 'draft')>Draft</option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    Tema event
                    <input name="theme" value="{{ old('theme', $event->theme) }}" required class="rounded-lg border border-zinc-200 px-3 py-2 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-emerald-500/25">
                </label>

                <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    Nama map
                    <input name="map_name" value="{{ old('map_name', $event->map_name) }}" required class="rounded-lg border border-zinc-200 px-3 py-2 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-emerald-500/25">
                </label>

                <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                    Prizepool
                    <span class="flex overflow-hidden rounded-lg border border-zinc-200 focus-within:border-emerald-400 focus-within:ring-2 focus-within:ring-emerald-100 dark:border-zinc-700 dark:focus-within:ring-emerald-500/25">
                        <span class="shrink-0 bg-emerald-50 px-3 py-2 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Rp</span>
                        <input inputmode="numeric" name="prizepool" value="{{ old('prizepool', $event->prizepool) }}" class="min-w-0 flex-1 bg-white px-3 py-2 outline-none dark:bg-zinc-950 dark:text-zinc-100" placeholder="500000">
                    </span>
                </label>
            </div>

            <div class="grid gap-5 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                <div>
                    <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Jadwal Event</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Pilih tanggal dan jam mulai/selesai sampai level menit.</p>
                </div>

                <div class="grid gap-4">
                    <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        Bulan
                        <select x-model="selectedMonth" name="schedule_month" @change="changeMonth()" class="rounded-lg border border-zinc-200 px-3 py-2 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-emerald-500/25">
                            @foreach ($dateMonths as $monthValue => $monthSlot)
                                <option value="{{ $monthValue }}">{{ $monthSlot['label'] }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="grid gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        <div class="flex items-center justify-between gap-3">
                            <span>Tanggal</span>
                        </div>
                        <input x-ref="eventDateInput" x-model="selectedDate" type="hidden" name="event_date" value="{{ $selectedDate }}" :value="selectedDate">
                        <div class="relative custom-date-dropdown" @click.outside="dateOpen = false">
                            <button type="button" class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:focus:ring-emerald-500/25" @click="dateOpen = ! dateOpen">
                                <span>
                                    <span class="block font-semibold text-zinc-900 dark:text-zinc-50" x-text="dateButtonLabel()"></span>
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400" x-text="slotSummary()"></span>
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">Pilih</span>
                            </button>

                            <div x-cloak x-show="dateOpen" x-transition class="absolute z-20 mt-2 max-h-72 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                <template x-for="[date, slot] in dateEntries()" :key="date">
                                    <button
                                        type="button"
                                        :disabled="! canPickDate(date, slot)"
                                        @click="chooseDate(date, slot)"
                                        class="mb-1 w-full rounded-lg border px-3 py-2 text-left text-xs font-semibold"
                                        :class="[
                                            slotToneClasses(slot),
                                            canPickDate(date, slot) ? 'cursor-pointer' : 'cursor-not-allowed',
                                            selectedDate === date ? 'border-emerald-500 bg-emerald-200 text-emerald-950 ring-2 ring-emerald-500 ring-offset-2 dark:border-emerald-300 dark:bg-emerald-500/25 dark:text-emerald-50 dark:ring-emerald-300 dark:ring-offset-zinc-800' : ''
                                        ]"
                                        :aria-pressed="selectedDate === date"
                                    >
                                        <span class="flex items-start justify-between gap-2">
                                            <span>
                                                <span class="block text-sm" x-text="slot.label"></span>
                                                <span class="mt-0.5 block text-[11px] font-medium" x-text="slot.statusLabel"></span>
                                            </span>
                                            <span x-show="slot.eventCount > 0" class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-bold ring-1 ring-emerald-100 dark:bg-zinc-900/80 dark:ring-zinc-700" x-text="slot.eventCount + ' event'"></span>
                                        </span>
                                        <span x-show="selectedDate === date" class="mt-1 inline-flex rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-bold text-white">Dipilih</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400" x-text="slotSummary()"></p>
                    </div>
                </div>

                <div class="grid gap-5">
                    <input x-ref="startTimeInput" type="hidden" name="start_time" value="{{ old('start_time', $event->start_time ? $event->start_time->format('H:i') : '15:00') }}" :value="start">
                    <input x-ref="endTimeInput" type="hidden" name="end_time" value="{{ old('end_time', $event->end_time ? $event->end_time->format('H:i') : '00:00') }}" :value="end">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span>Jam mulai</span>
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">Slot merah tidak bisa dipilih.</span>
                            </div>
                            <div class="relative custom-time-dropdown" @click.outside="startOpen = false">
                                <button type="button" class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-900 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:ring-emerald-500/25" @click="startOpen = ! startOpen">
                                    <span x-text="start || 'Pilih jam mulai'"></span>
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Pilih</span>
                                </button>

                                <div x-cloak x-show="startOpen" x-transition class="absolute z-20 mt-2 grid max-h-64 w-full grid-cols-3 gap-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                            <template x-for="option in timeOptions" :key="`start-${option.value}`">
                                <button
                                    type="button"
                                    :disabled="! canSelectStart(option.value)"
                                            @click="chooseStart(option.value); startOpen = false"
                                            class="rounded-lg border px-2 py-1.5 text-center text-xs font-semibold"
                                    :class="[
                                                canSelectStart(option.value) ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-400/50 dark:bg-emerald-500/15 dark:text-emerald-100 dark:hover:bg-emerald-500/20' : 'cursor-not-allowed border-red-200 bg-red-50 text-red-700 dark:border-red-400/50 dark:bg-red-500/15 dark:text-red-100',
                                        start === option.value ? 'border-emerald-500 bg-emerald-200 text-emerald-950 ring-2 ring-emerald-500 ring-offset-2 dark:border-emerald-300 dark:bg-emerald-500/25 dark:text-emerald-50 dark:ring-emerald-300 dark:ring-offset-zinc-800' : ''
                                    ]"
                                    x-text="option.label"
                                ></button>
                            </template>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            <span>Jam selesai</span>
                            <div class="relative custom-time-dropdown" @click.outside="endOpen = false">
                                <button type="button" class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm font-semibold text-zinc-900 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-50 dark:focus:ring-emerald-500/25" @click="endOpen = ! endOpen">
                                    <span x-text="end || 'Pilih jam selesai'"></span>
                                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Pilih</span>
                                </button>

                                <div x-cloak x-show="endOpen" x-transition class="absolute z-20 mt-2 grid max-h-64 w-full grid-cols-3 gap-1 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                            <template x-for="option in timeOptions" :key="`end-${option.value}`">
                                <button
                                    type="button"
                                    :disabled="! canSelectEnd(option.value)"
                                            @click="chooseEnd(option.value); endOpen = false"
                                            class="rounded-lg border px-2 py-1.5 text-center text-xs font-semibold"
                                    :class="[
                                                canSelectEnd(option.value) ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 dark:border-emerald-400/50 dark:bg-emerald-500/15 dark:text-emerald-100 dark:hover:bg-emerald-500/20' : 'cursor-not-allowed border-red-200 bg-red-50 text-red-700 dark:border-red-400/50 dark:bg-red-500/15 dark:text-red-100',
                                        end === option.value ? 'border-emerald-500 bg-emerald-200 text-emerald-950 ring-2 ring-emerald-500 ring-offset-2 dark:border-emerald-300 dark:bg-emerald-500/25 dark:text-emerald-50 dark:ring-emerald-300 dark:ring-offset-zinc-800' : ''
                                    ]"
                                    x-text="option.label"
                                ></button>
                            </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                    <div>
                        <span class="font-semibold">Slot tanggal ini:</span>
                        <span x-text="slotSummary()"></span>
                    </div>

                    <div class="mt-3" x-show="occupiedRanges().length">
                        <p class="font-semibold">Jam terisi:</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="range in occupiedRanges()" :key="range.label">
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-100 dark:bg-zinc-900 dark:text-emerald-200 dark:ring-zinc-700" x-text="range.label"></span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                Poster Event (Opsional)
                <input type="file" name="poster" accept="image/png,image/jpeg,image/webp" class="rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-3 py-3 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">
                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Format JPG, PNG, atau WebP. Maksimal {{ \App\Models\Event::POSTER_MAX_MEGABYTES }} MB sesuai limit server saat ini.</span>
            </label>

            @if ($event->poster_url)
                <div class="flex items-center gap-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-100">
                    <img src="{{ $event->poster_url }}" alt="" class="size-16 rounded-lg object-cover">
                    Poster saat ini akan diganti jika upload poster baru.
                </div>
            @endif

            <label class="grid gap-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                Deskripsi
                <textarea name="description" rows="5" class="rounded-lg border border-zinc-200 px-3 py-2 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:focus:ring-emerald-500/25">{{ old('description', $event->description) }}</textarea>
            </label>

            <div class="flex justify-end gap-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
                <a href="{{ route('admin.events.index') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-zinc-800">Batal</a>
                <button
                    :disabled="selectedRangeInvalid()"
                    class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm"
                    :class="selectedRangeInvalid() ? 'cursor-not-allowed bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                >Simpan Event</button>
            </div>
            </form>

        </div>
    </div>
</x-layouts.admin>
