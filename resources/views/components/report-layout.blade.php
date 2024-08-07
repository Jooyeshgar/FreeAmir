<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Report' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @page {
            size: A4;
            margin: 20mm;
        }

        td {
            white-space: nowrap;
            overflow: hidden;
        }
    </style>
    @stack('styles')
</head>

<body class="printable" dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">

    <main class="mx-auto" style="width: 210mm;">
        {{ $slot }}
    </main>

    @stack('scripts')
</body>

</html>
