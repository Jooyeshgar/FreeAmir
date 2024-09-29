<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-screen" dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">

    <span class="flex items-center fixed left-0 right-0 top-0 bottom-0">
        <img src="/images/background.jpg" alt="" class="w-full h-full">
    </span>
    <div x-data="{ open: false }" class="min-[1430px]:w-[1430px] m-auto">
        <x-header />

        {{ $slot }}

    </div>

</body>

</html>
