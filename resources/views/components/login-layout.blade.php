<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light" data-force-light="true">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-w-0 bg-gray-200">
    <main class="mx-auto flex min-h-screen min-w-0 flex-col bg-gray-200 p-0">
        {{ $slot }}
    </main>
</body>

</html>
