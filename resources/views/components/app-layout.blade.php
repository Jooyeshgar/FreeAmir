@props([
    'title' => config('app.name'),
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="relative min-h-screen overflow-x-hidden bg-base-100 text-base-content" dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">

    <div class="app-background" aria-hidden="true"></div>
    <div x-data="{ open: false }" class="relative z-10 min-[1430px]:w-[1430px] mx-auto">
        <x-header />

        {{ $slot }}

    </div>
    @stack('scripts')

    @stack('footer')
</body>

</html>
