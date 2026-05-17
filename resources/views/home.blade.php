<x-layouts.public title="Queenlin">
    <section class="mx-auto grid min-h-[calc(100vh-9rem)] w-full max-w-6xl items-center gap-10 px-5 py-10 sm:px-6 lg:grid-cols-[1.05fr_.95fr] lg:px-8">
        <div>
            <div class="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-white px-3 py-1 text-sm font-medium text-emerald-700 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                <span class="size-2 rounded-full bg-emerald-400"></span>
                Shoutcaster schedule, cleaner and faster
            </div>

            <h1 class="max-w-3xl text-5xl font-semibold leading-tight text-emerald-950 dark:text-zinc-50 sm:text-6xl">Queenlin</h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-emerald-900/80 dark:text-zinc-300">
                Cek jadwal shoutcasting Queenlin, lihat tanggal yang masih kosong, dan temukan detail event dalam tampilan yang rapi.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('schedule') }}" class="rounded-lg bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Lihat Jadwal
                </a>
            </div>
        </div>

        <div class="rounded-lg border border-emerald-100 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-emerald-50 pb-4 dark:border-zinc-800">
                <div>
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Upcoming</p>
                    <h2 class="text-xl font-semibold text-emerald-950 dark:text-zinc-50">Event Terdekat</h2>
                </div>
                <span class="rounded-full bg-lime-100 px-3 py-1 text-xs font-semibold text-lime-800 dark:bg-lime-400/15 dark:text-lime-100 dark:ring-1 dark:ring-lime-300/30">15.00-00.00 WIB</span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($upcomingEvents as $event)
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50/50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-emerald-950 dark:text-zinc-50">{{ $event->title }}</h3>
                                <p class="mt-1 text-sm text-emerald-800 dark:text-zinc-300">{{ $event->event_date->translatedFormat('d M Y') }} · {{ $event->start_time->format('H:i') }}-{{ $event->end_time->format('H:i') }} WIB</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-100 dark:bg-zinc-800 dark:text-emerald-200 dark:ring-zinc-700">{{ $event->map_name }}</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-emerald-200 bg-emerald-50/60 p-8 text-center dark:border-emerald-500/30 dark:bg-zinc-950">
                        <p class="font-semibold text-emerald-950 dark:text-zinc-50">No events yet.</p>
                        <p class="mt-1 text-sm text-emerald-800 dark:text-zinc-300">Schedule Queenlin masih available untuk event baru.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.public>
