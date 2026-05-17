<x-layouts.public title="Schedule">
    <section class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-semibold uppercase tracking-normal text-emerald-600 dark:text-emerald-400">Schedule</p>
                <h1 class="mt-2 text-3xl font-semibold text-emerald-950 dark:text-zinc-50">Pilih tanggal event</h1>
                <p class="mt-2 max-w-2xl text-emerald-800 dark:text-zinc-300">Warna tanggal dihitung dari total durasi event pada hari tersebut.</p>
            </div>

            <form method="GET" action="{{ route('schedule') }}" class="flex gap-2">
                <select name="month" class="rounded-lg border border-emerald-100 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100 dark:focus:border-emerald-400 dark:focus:ring-emerald-500/25" onchange="this.form.submit()">
                    @foreach ($monthOptions as $option)
                        <option value="{{ $option->format('Y-m') }}" @selected($option->isSameMonth($month))>{{ $option->translatedFormat('F Y') }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem]">
            <div class="rounded-lg border border-emerald-100 bg-white p-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-4">
                <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 sm:gap-2 sm:text-xs">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)
                        <div class="py-2">{{ $day }}</div>
                    @endforeach
                </div>

                <div class="mt-2 grid grid-cols-7 gap-1.5 sm:gap-2">
                    @foreach ($calendarDays as $day)
                        <a
                            href="{{ route('schedule', ['month' => $month->format('Y-m'), 'date' => $day['date']->toDateString()]) }}"
                            class="{{ $day['density']['classes'] }} {{ $day['isSelected'] ? 'border-emerald-500 bg-emerald-100 ring-2 ring-emerald-500 ring-offset-1 ring-offset-white dark:border-emerald-300 dark:bg-emerald-500/25 dark:ring-emerald-300 dark:ring-offset-zinc-900 sm:ring-offset-2' : '' }} {{ $day['isCurrentMonth'] ? '' : 'opacity-35 dark:opacity-45' }} relative flex min-h-20 overflow-hidden rounded-lg border p-1.5 text-left transition hover:-translate-y-0.5 hover:shadow-sm dark:hover:bg-emerald-500/20 sm:p-2"
                        >
                            <span aria-hidden="true" class="pointer-events-none absolute -bottom-1 -right-0.5 text-4xl font-black leading-none text-emerald-900/5 dark:text-emerald-200/12 sm:-bottom-2 sm:-right-1 sm:text-5xl">{{ $day['date']->day }}</span>
                            <span class="relative z-10 flex size-full flex-col justify-between">
                            <span class="flex items-start justify-between gap-1 sm:gap-2">
                                <span class="text-xs font-semibold sm:text-sm">{{ $day['date']->day }}</span>
                                @if ($day['eventCount'] > 0)
                                    <span class="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-semibold text-white sm:size-5 sm:text-[11px]">{{ $day['eventCount'] }}</span>
                                @endif
                            </span>
                            <span class="min-w-0 text-[9px] font-semibold leading-none sm:flex sm:items-center sm:gap-1 sm:text-[11px] sm:font-medium sm:leading-normal">
                                <span class="{{ $day['density']['dot'] }} hidden size-2 shrink-0 rounded-full sm:inline-block"></span>
                                <span class="block truncate sm:hidden">{{ $day['density']['shortLabel'] }}</span>
                                <span class="hidden truncate sm:block">{{ $day['density']['label'] }}</span>
                            </span>
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium dark:text-zinc-100">
                    @foreach ([[60, null], [300, null], [480, null], [0, \App\Models\ManualFullDate::STATUS_OFF]] as [$minutes, $manualStatus])
                        @php($density = \App\Models\Event::densityForMinutes($minutes, $manualStatus))
                        <span class="{{ $density['classes'] }} inline-flex items-center gap-2 rounded-full border px-3 py-1">
                            <span class="{{ $density['dot'] }} size-2 rounded-full"></span>
                            {{ $density['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            <aside class="rounded-lg border border-emerald-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">{{ $selectedDate->translatedFormat('l, d F Y') }}</p>

                <div class="mt-3 grid gap-1.5">
                    @if ($selectedManualStatus === \App\Models\ManualFullDate::STATUS_OFF)
                        <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm font-medium text-sky-800 dark:border-sky-400/40 dark:bg-sky-500/15 dark:text-sky-100">
                            Queenlin sedang libur pada tanggal ini.
                        </div>
                    @endif

                    @forelse ($selectedEvents as $event)
                        <a
                            href="{{ route('schedule', ['month' => $month->format('Y-m'), 'date' => $selectedDate->toDateString(), 'event' => $event->id]) }}"
                            class="{{ $selectedEvent?->is($event) ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-100 dark:border-emerald-300 dark:bg-emerald-500/15 dark:ring-emerald-300/30' : 'border-zinc-100 bg-white hover:bg-emerald-50/60 dark:border-zinc-800 dark:bg-zinc-950/40 dark:hover:bg-zinc-800/70' }} rounded-lg border px-3 py-2 transition"
                        >
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                                <div class="min-w-0 overflow-hidden">
                                    <p class="schedule-marquee text-sm font-semibold text-emerald-950 dark:text-zinc-50" title="{{ $event->map_name }}">
                                        <span>{{ $event->map_name }}</span>
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-zinc-600 dark:text-zinc-300">
                                        {{ $event->title }}
                                        @if ($event->progress_status === \App\Models\Event::PROGRESS_DONE)
                                            <span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-100">Selesai</span>
                                        @elseif ($event->progress_status === \App\Models\Event::PROGRESS_CANCELED)
                                            <span class="ml-1 rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-500/20 dark:text-red-100">Cancel</span>
                                        @endif
                                    </p>
                                </div>
                                <p class="shrink-0 rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-bold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-100 dark:text-emerald-900 dark:ring-emerald-300">{{ $event->start_time->format('H:i') }}-{{ $event->end_time->format('H:i') }} WIB</p>
                            </div>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 p-4 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">
                            Belum ada event pada tanggal ini.
                        </div>
                    @endforelse
                </div>
            </aside>
        </div>

        <div class="mt-8">
            <h2 class="text-xl font-semibold text-emerald-950 dark:text-zinc-50">Detail Event</h2>

            <div class="mt-4 grid gap-4">
                @if ($selectedEvent)
                    <article
                        class="overflow-hidden rounded-lg border border-emerald-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                        x-data="{
                            open: false,
                            zoom: 1,
                            panX: 0,
                            panY: 0,
                            panning: false,
                            panStartX: 0,
                            panStartY: 0,
                            panOriginX: 0,
                            panOriginY: 0,
                            panMoved: false,
                            suppressCloseClick: false,
                            openPoster() {
                                this.open = true;
                                this.resetZoom();
                            },
                            zoomBy(amount) {
                                this.zoom = Math.min(Math.max(this.zoom + amount, 1), 3);
                                if (this.zoom === 1) {
                                    this.resetPan();
                                }
                            },
                            wheelZoom(event) {
                                event.preventDefault();
                                this.zoomBy(event.deltaY < 0 ? 0.15 : -0.15);
                            },
                            resetPan() {
                                this.panX = 0;
                                this.panY = 0;
                                this.panning = false;
                            },
                            resetZoom() {
                                this.zoom = 1;
                                this.resetPan();
                            },
                            startPan(event) {
                                if (this.zoom <= 1 || event.button !== 0) {
                                    return;
                                }

                                this.panning = true;
                                this.panMoved = false;
                                this.panStartX = event.clientX;
                                this.panStartY = event.clientY;
                                this.panOriginX = this.panX;
                                this.panOriginY = this.panY;
                                event.currentTarget.setPointerCapture?.(event.pointerId);
                            },
                            movePan(event) {
                                if (! this.panning) {
                                    return;
                                }

                                const nextPanX = this.panOriginX + event.clientX - this.panStartX;
                                const nextPanY = this.panOriginY + event.clientY - this.panStartY;

                                if (Math.abs(nextPanX - this.panOriginX) > 3 || Math.abs(nextPanY - this.panOriginY) > 3) {
                                    this.panMoved = true;
                                }

                                this.panX = nextPanX;
                                this.panY = nextPanY;
                            },
                            stopPan() {
                                if (this.panning && this.panMoved) {
                                    this.suppressCloseClick = true;
                                    setTimeout(() => this.suppressCloseClick = false, 0);
                                }

                                this.panning = false;
                            },
                            closeFromBackdrop() {
                                if (this.suppressCloseClick) {
                                    return;
                                }

                                this.open = false;
                            },
                        }"
                    >
                        <button type="button" class="block aspect-[16/9] w-full bg-emerald-50 dark:bg-zinc-800" @click="openPoster()" aria-label="Buka poster event">
                            @if ($selectedEvent->poster_url)
                                <img src="{{ $selectedEvent->poster_url }}" alt="Poster {{ $selectedEvent->title }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center p-8 text-center text-sm font-medium text-emerald-700 dark:text-emerald-300">Poster belum tersedia</div>
                            @endif
                        </button>

                        <div class="p-5 sm:p-6">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-2xl font-semibold text-emerald-950 dark:text-zinc-50">{{ $selectedEvent->title }}</h3>
                                        @if ($selectedEvent->progress_status === \App\Models\Event::PROGRESS_DONE)
                                            <span class="inline-flex items-center rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white">Selesai</span>
                                        @elseif ($selectedEvent->progress_status === \App\Models\Event::PROGRESS_CANCELED)
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700 ring-1 ring-red-200 dark:bg-red-500/20 dark:text-red-100 dark:ring-red-400/30">Cancel</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-400/30">{{ $selectedEvent->start_time->format('H:i') }}-{{ $selectedEvent->end_time->format('H:i') }} WIB</p>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-100 dark:bg-zinc-800 dark:text-emerald-200 dark:ring-zinc-700">{{ $selectedEvent->durationMinutes() }} menit</span>
                            </div>

                            <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-3">
                                <div class="rounded-lg bg-emerald-50 p-4 text-center dark:bg-emerald-500/10"><dt class="font-semibold text-emerald-950 dark:text-zinc-50">Map</dt><dd class="mt-1 text-emerald-800 dark:text-emerald-200">{{ $selectedEvent->map_name }}</dd></div>
                                <div class="rounded-lg bg-emerald-50 p-4 text-center dark:bg-emerald-500/10"><dt class="font-semibold text-emerald-950 dark:text-zinc-50">Tema</dt><dd class="mt-1 text-emerald-800 dark:text-emerald-200">{{ $selectedEvent->theme }}</dd></div>
                                <div class="rounded-lg bg-emerald-50 p-4 text-center dark:bg-emerald-500/10"><dt class="font-semibold text-emerald-950 dark:text-zinc-50">Prizepool</dt><dd class="mt-1 text-emerald-800 dark:text-emerald-200">{{ $selectedEvent->formattedPrizepool() }}</dd></div>
                            </dl>

                            @if ($selectedEvent->description)
                                <div class="mt-6">
                                    <h4 class="text-sm font-semibold text-emerald-950 dark:text-zinc-50">Description</h4>
                                    <p class="mt-2 whitespace-pre-line leading-7 text-emerald-900/80 dark:text-zinc-300">{{ $selectedEvent->description }}</p>
                                </div>
                            @endif
                        </div>

                        @if ($selectedEvent->poster_url)
                            <div
                                x-cloak
                                x-show="open"
                                x-transition.opacity
                                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                                @keydown.escape.window="open = false"
                            >
                                <div class="absolute right-4 top-4 flex gap-2">
                                    <button type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-zinc-900" @click="zoomBy(0.25)">Zoom In</button>
                                    <button type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-zinc-900" @click="zoomBy(-0.25)">Zoom Out</button>
                                    <button type="button" class="rounded-lg bg-white px-3 py-2 text-sm font-semibold text-zinc-900" @click="resetZoom()">Reset</button>
                                    <button type="button" class="rounded-lg bg-red-500 px-3 py-2 text-sm font-semibold text-white" @click="open = false">Close</button>
                                </div>
                                <div
                                    class="flex h-[85vh] w-[92vw] items-center justify-center overflow-hidden rounded-lg bg-black/20 p-2"
                                    @click.self="closeFromBackdrop()"
                                    @contextmenu.prevent
                                    @pointerdown.prevent="startPan($event)"
                                    @pointermove.window="movePan($event)"
                                    @pointerup.window="stopPan()"
                                    @pointercancel.window="stopPan()"
                                    @wheel.prevent="wheelZoom($event)"
                                >
                                    <img
                                        src="{{ $selectedEvent->poster_url }}"
                                        alt="Poster {{ $selectedEvent->title }}"
                                        class="max-h-full max-w-full origin-center select-none object-contain"
                                        :class="zoom > 1 ? (panning ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-default'"
                                        :style="`transform: translate3d(${panX}px, ${panY}px, 0) scale(${zoom})`"
                                        draggable="false"
                                        @dragstart.prevent
                                    >
                                </div>
                            </div>
                        @endif
                    </article>
                @else
                    <div class="rounded-lg border border-dashed border-emerald-200 bg-white p-10 text-center shadow-sm dark:border-emerald-500/30 dark:bg-zinc-900 dark:text-zinc-100">
                        <h3 class="text-lg font-semibold text-emerald-950 dark:text-zinc-50">No events yet</h3>
                        <p class="mt-2 text-emerald-800 dark:text-zinc-300">
                            @if ($selectedManualStatus === \App\Models\ManualFullDate::STATUS_OFF)
                                Queenlin sedang libur pada tanggal ini.
                            @else
                                Belum ada event pada tanggal ini.
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.public>
