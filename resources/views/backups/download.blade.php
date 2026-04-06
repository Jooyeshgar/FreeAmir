<x-app-layout>

    <script>
        window.onload = function() {
            window.location.href = "{{ $downloadUrl }}";

            setTimeout(function() {
                window.location.href = "{{ $redirectUrl }}";
            }, 100);
        };
    </script>

</x-app-layout>
