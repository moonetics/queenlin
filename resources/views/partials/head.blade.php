<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/assets/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/icon.png">

@vite(['resources/css/app.css', 'resources/js/app.js'])
<script>
    (() => {
        const storedAppearance = window.localStorage.getItem('flux.appearance');
        const appearance = ['dark', 'light'].includes(storedAppearance) ? storedAppearance : 'light';

        window.localStorage.setItem('flux.appearance', appearance);
        document.documentElement.classList.toggle('dark', appearance === 'dark');
    })();
</script>
@fluxAppearance
