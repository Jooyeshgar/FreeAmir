<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css','resources/css/custom.css'])
    </head>
    <body>
        <main class="bg-gray-200 mx-auto  min-h-screen p-0 flex flex-col  ">
            {{ $slot }}
        </main>
    </body>
</html>
