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

<body class="relative min-h-screen overflow-x-hidden bg-base-200 text-base-content">

    <x-header />

    <main class="mx-auto mt-3 w-full max-w-[1430px] px-3 sm:mt-5 sm:px-4 lg:px-5">
        {{ $slot }}
    </main>

    <footer class="mt-8 px-3 pb-4 text-center text-xs opacity-60">
        {{ __('Integrated Accounting and Human Resources System') }} {{ __('Version :version', ['version' => config('app.version')]) }}
    </footer>

    @stack('scripts')

    @stack('footer')
</body>

</html>
