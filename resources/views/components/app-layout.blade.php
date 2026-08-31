@props([
    'title' => config('app.name'),
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" data-theme="light">

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

<body x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" :class="{ 'overflow-hidden': mobileMenuOpen }" class="relative min-h-screen overflow-x-hidden bg-base-200 text-base-content">

    <x-header />

    <main class="min-[1430px]:w-[1430px] mx-auto mt-5" :class="{ 'pointer-events-none select-none blur-sm opacity-75': mobileMenuOpen }" :inert="mobileMenuOpen">
        {{ $slot }}
    </main>

    <footer class="mt-8 text-center text-xs opacity-60 pb-4">
        {{ __('Integrated Accounting and Human Resources System') }} {{ __('Version :version', ['version' => config('app.version')]) }}
    </footer>

    @stack('scripts')

    @stack('footer')
</body>

</html>
