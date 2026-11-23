<!DOCTYPE html>
@php($themePreference = auth()->user()?->theme_preference?->value ?? 'system')
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-theme-preference="{{ $themePreference }}"
    @class(['dark' => $themePreference === 'dark'])
>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script>
        // Resolve "system" before the first paint so a dark OS never flashes
        // the light theme. resources/js/utils/colorScheme.js keeps it in sync.
        (function () {
            var root = document.documentElement;
            var pref = root.dataset.themePreference;
            var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.classList.toggle('dark', dark);
            root.style.colorScheme = dark ? 'dark' : 'light';
        })();
    </script>
    <script>
        // iOS keeps pinch-zoom despite maximum-scale but stops auto-zooming into
        // inputs below 16px. Elsewhere (Android) it would block zooming (WCAG 1.4.4).
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            document.querySelector('meta[name=viewport]').content += ', maximum-scale=1';
        }
    </script>

    <title inertia>{{ config('app.name', 'gymportal.io') }}</title>

    {{-- Brand typeface, self-hosted (no third-party request). The latin subset
         covers German; latin-ext loads only when such glyphs appear. --}}
    <link rel="preload" href="{{ Vite::asset('resources/fonts/archivo-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    @routes
    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @inertiaHead

    <!-- Laravel Config für Frontend -->
    <script>
        window.Laravel = {
            user: @json(auth()->user()),
            csrfToken: '{{ csrf_token() }}'
        };
    </script>
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
