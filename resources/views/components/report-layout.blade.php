<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Report' }}</title>
    <style>
        @page {
            size: A4;
            margin: 20mm;
        }

        @font-face {
            font-family: "Vazir";
            src: url(data/font/Vazir.woff);
        }

        main,
        header {
            max-width: 30cm;
            margin: 0 auto;
        }

        table {

            border-collapse: collapse;
            width: 100%;
        }

        th {
            border: 1px solid black;
            padding: 10px;
            font-size: 10px;
        }

        thead {
            display: table-header-group;
        }

        .transactions td {
            border-left: 1px solid;
            border-right: 1px solid;
            padding: 10px;
            font-size: 10px;
        }



        body {
            font-family: "Vazir"
        }

        @media print {
            table {
                page-break-after: auto;
            }
        }
    </style>
    @stack('styles')
</head>

<body dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">
    <header>
        <h1>{{ $title ?? 'Report Header' }}</h1>
    </header>

    <main>
        {{ $slot }}
    </main>

    @stack('scripts')
</body>

</html>
