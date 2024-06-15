<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>{{ $title ?? 'Report' }}</title>

    <style>
        @media print {
            .print-hidden{
                display: none;
            }
        }
        @page {
            size: A4;
            margin: 20mm;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-Thin.woff2') format('woff2');
            font-weight: 100;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-ExtraLight.woff2') format('woff2');
            font-weight: 200;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-Light.woff2') format('woff2');
            font-weight: 300;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-Medium.woff2') format('woff2');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-SemiBold.woff2') format('woff2');
            font-weight: 600;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-Bold.woff2') format('woff2');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-ExtraBold.woff2') format('woff2');
            font-weight: 800;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: Vazirmatn;
            src: url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-Black.woff2') format('woff2');
            font-weight: 900;
            font-style: normal;
            font-display: swap;
        }


        * {
            font-family: Vazirmatn, sans-serif !important;
        }


        main,
        header {
            max-width: 20cm;
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


        .transactions td {
            border-left: 1px solid;
            border-right: 1px solid;
            padding: 10px;
            font-size: 10px;
        }



        body {
            font-family: Vazirmatn, sans-serif !important;
        }


    </style>
    @stack('styles')
</head>

<body dir="{{ app()->getLocale() == 'fa' ? 'rtl' : 'ltr' }}">
    <header class="print-hidden">
        <h1>{{ $title ?? 'Report Header' }}</h1>
    </header>

    <main>
        {{ $slot }}
    </main>

    @stack('scripts')
</body>

</html>
