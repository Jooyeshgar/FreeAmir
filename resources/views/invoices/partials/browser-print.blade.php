@if ($autoPrint ?? false)
    <style>
        @font-face {
            font-family: sahel;
            src: url({{ asset('fonts/Sahel.ttf') }});
        }
    </style>
    <script>
        window.addEventListener('load', async () => {
            await document.fonts.ready;
            window.print();
        });
    </script>
@endif
