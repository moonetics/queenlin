@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="queenlin-public-shell min-h-screen bg-[#f2fbf4] text-emerald-950 antialiased dark:bg-zinc-950 dark:text-emerald-50">
        <div class="min-h-screen">
            <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-5 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3 font-semibold">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-emerald-500 text-white">
                        <x-app-logo-icon class="size-5" />
                    </span>
                    <span class="text-lg">Queenlin</span>
                </a>

                <nav class="flex items-center gap-2 text-sm font-medium text-emerald-900 dark:text-emerald-100">
                    <a href="{{ route('schedule') }}" class="rounded-lg px-3 py-2 hover:bg-white/80 dark:hover:bg-zinc-900">Schedule</a>
                    @auth
                        <a href="{{ route('admin.events.index') }}" class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">Admin</a>
                    @endauth
                    <x-theme-toggle />
                </nav>
            </header>

            <main>
                {{ $slot }}
            </main>

            <footer class="mx-auto mt-12 w-full max-w-6xl px-5 py-8 text-sm text-emerald-800 dark:text-emerald-200 sm:px-6 lg:px-8">
                <div class="border-t border-emerald-100 pt-6 dark:border-zinc-800">Managed by Squad Limpul · {{ now()->year }}</div>
            </footer>
        </div>
    </body>
</html>
