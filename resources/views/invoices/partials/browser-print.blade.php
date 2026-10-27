@isset($autoPrint)
    <style>
        @page {
            size: A4 {{ $orientation }};
        }
        @media print {
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        @font-face {
            font-family: sahel;
            src: url({{ asset('fonts/Sahel.ttf') }});
        }
        @font-face {
            font-family: sahel;
            font-weight: bold;
            src: url({{ asset('fonts/Sahel-Bold.ttf') }});
        }
    </style>
@endisset
@if ($autoPrint ?? false)
    <script>
        window.addEventListener('load', async () => {
            await document.fonts.ready;
            window.print();
        });
    </script>
@endif
