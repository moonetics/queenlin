@php
    $settingsNavItems = [
        ['label' => __('Profile'), 'route' => 'profile.edit'],
        ['label' => __('Discord'), 'route' => 'discord.edit'],
        ['label' => __('Security'), 'route' => 'security.edit'],
    ];
@endphp

<div class="rounded-lg border border-emerald-100 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
    <div class="grid gap-0 lg:grid-cols-[14rem_1fr]">
        <aside class="border-b border-emerald-100 p-4 dark:border-zinc-800 lg:border-b-0 lg:border-r">
            <nav class="grid gap-2 text-sm font-semibold text-zinc-700 dark:text-zinc-300" aria-label="{{ __('Settings') }}">
                @foreach ($settingsNavItems as $item)
                    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['route']) ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }} rounded-lg px-3 py-2.5">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <section class="min-w-0 p-5 sm:p-6">
            <div class="max-w-2xl">
                <h2 class="text-xl font-semibold text-zinc-950 dark:text-zinc-50">{{ $heading ?? '' }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $subheading ?? '' }}</p>

                <div class="mt-6 w-full max-w-lg">
                    {{ $slot }}
                </div>
            </div>
        </section>
    </div>
</div>
