@props([
    'title' => config('app.name'),
])

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

<body class="min-h-screen bg-base-100 text-base-content" dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">

    <span class="flex items-center fixed left-0 right-0 top-0 bottom-0 -z-10">
        <img src="/images/background.jpg" alt="" class="w-full h-full object-cover opacity-30 dark:opacity-10 dark:brightness-50">
    </span>
    <div x-data="{ open: false }" class="min-[1430px]:w-[1430px] m-auto">
        <x-header />

        {{ $slot }}

    </div>
    @stack('scripts')

    @stack('footer')
</body>

</html>
