<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite('resources/css/app.css')
  </head>
  <body>
    <x-header/>

    <main>
      {{ $slot }}
    </main>

    <x-footer/>
  </body>
</html>