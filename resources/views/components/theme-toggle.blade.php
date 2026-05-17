@props(['class' => ''])

<button
    type="button"
    class="queenlin-theme-toggle inline-flex size-10 items-center justify-center rounded-lg bg-white text-emerald-800 shadow-sm ring-1 ring-emerald-100 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:bg-zinc-900 dark:text-emerald-200 dark:ring-zinc-700 dark:hover:bg-zinc-800 dark:focus:ring-offset-zinc-950 {{ $class }}"
    x-data="{
        dark: false,
        init() {
            const appearance = window.localStorage.getItem('flux.appearance');

            this.dark = appearance === 'dark';
            document.documentElement.classList.toggle('dark', this.dark);
        },
        apply() {
            const appearance = this.dark ? 'dark' : 'light';

            window.localStorage.setItem('flux.appearance', appearance);
            document.documentElement.classList.toggle('dark', this.dark);
            window.Flux?.applyAppearance?.(appearance);
            window.dispatchEvent(new CustomEvent('queenlin-theme-changed', { detail: { appearance } }));
        },
        toggle() {
            this.dark = ! this.dark;
            this.apply();
        },
    }"
    @click="toggle()"
    :aria-label="dark ? 'Aktifkan light mode' : 'Aktifkan dark mode'"
    :title="dark ? 'Light mode' : 'Dark mode'"
    :aria-pressed="dark.toString()"
>
    <svg x-show="! dark" class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>

    <svg x-cloak x-show="dark" class="size-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M20.99 12.79A8.5 8.5 0 1 1 11.21 3a6.5 6.5 0 0 0 9.78 9.79Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
    </svg>

    <span class="sr-only" x-text="dark ? 'Light mode' : 'Dark mode'">Toggle theme</span>
</button>
