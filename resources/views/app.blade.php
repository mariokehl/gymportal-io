<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />

    <title inertia>{{ config('app.name', 'gymportal.io') }}</title>

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
