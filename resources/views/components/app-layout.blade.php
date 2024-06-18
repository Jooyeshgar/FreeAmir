<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen" dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">
    <x-header />
    <div class="  -z-0">
        <main class="max-w-6xl mx-auto py-0 lg:pb-14 px-2 lg:pd-x-0  ">
            {{ $slot }}
        </main>
    </div>

</body>

</html>
