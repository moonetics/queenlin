@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="queenlin-admin-shell min-h-screen bg-[#f4fbf6] text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside class="border-b border-emerald-100 bg-white/95 px-5 py-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/95 lg:min-h-screen lg:border-b-0 lg:border-r lg:px-6 lg:py-6">
                <div class="flex items-center justify-between gap-4 lg:block">
                    <a href="{{ route('admin.events.index') }}" class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-sm shadow-emerald-200">
                            <x-app-logo-icon class="size-6" />
                        </span>
                        <span>
                            <span class="block text-sm font-semibold text-zinc-950 dark:text-zinc-50">Queenlin</span>
                            <span class="block text-xs font-medium text-emerald-700 dark:text-emerald-300">Caster Admin</span>
                        </span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                        @csrf
                        <button class="rounded-lg bg-zinc-950 px-3 py-2 text-xs font-semibold text-white">Logout</button>
                    </form>
                </div>

                <nav class="mt-6 grid gap-2 text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                    <a href="{{ route('admin.events.index') }}" class="{{ request()->routeIs('admin.events.*') ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-900' }} rounded-lg px-3 py-2.5">
                        Events
                    </a>
                    <a href="{{ route('schedule') }}" class="rounded-lg px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                        Public Schedule
                    </a>
                    <a href="{{ route('home') }}" class="rounded-lg px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                        Homepage
                    </a>
                </nav>

                <div class="mt-8 hidden rounded-lg border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100 lg:block">
                    <p class="font-semibold">Operational window</p>
                    <p class="mt-1 text-emerald-800 dark:text-emerald-300">15.00 - 00.00 WIB</p>
                </div>

            </aside>

            <div class="min-w-0">
                <header class="border-b border-emerald-100 bg-white/80 px-5 py-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/80 sm:px-8 lg:px-10">
                    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-normal text-emerald-600 dark:text-emerald-300">Queenlin Admin</p>
                            <h1 class="mt-1 text-lg font-semibold text-zinc-950 dark:text-zinc-50">{{ $title ?? 'Dashboard' }}</h1>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-theme-toggle />
                            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                                <button
                                    type="button"
                                    class="flex size-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white shadow-sm ring-2 ring-emerald-100 hover:bg-emerald-700"
                                    @click="open = ! open"
                                    aria-label="Open profile menu"
                                >
                                    {{ auth()->user()->initials() }}
                                </button>

                                <div
                                    x-cloak
                                    x-show="open"
                                    x-transition
                                    @click.outside="open = false"
                                    class="absolute right-0 z-50 mt-3 w-64 rounded-lg border border-zinc-100 bg-white p-4 shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
                                >
                                    <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ auth()->user()->name }}</p>
                                    <p class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</p>

                                    <a href="{{ route('settings') }}" class="mt-4 block rounded-lg px-3 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-emerald-50 hover:text-emerald-800 hover:ring-emerald-100 dark:text-zinc-200 dark:ring-zinc-700 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200">
                                        Settings
                                    </a>

                                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                                        @csrf
                                        <button class="w-full rounded-lg bg-zinc-950 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Logout</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="px-5 py-6 sm:px-8 lg:px-10 lg:py-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
