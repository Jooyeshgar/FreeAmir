<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="corporate">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite('resources/css/app.css')
  </head>
  <body>
    <x-header/>
    <div class="bg-neutral-200">
      <main class="max-w-5xl mx-auto py-12">
        {{ $slot }}
      </main>
    </div>
    <x-footer/>
  </body>
</html>