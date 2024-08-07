@props([
    'title' => config('app.name'),
])
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="printable" dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">
    <div class="">
        <main class="max-w-6xl mx-auto py-0 lg:pb-14 px-2 lg:pd-x-0  ">
            {{ $slot }}
        </main>
    </div>

</body>

</html>
