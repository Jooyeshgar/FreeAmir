@props([
    'title' => config('app.name'),
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf_token" content="{{ csrf_token() }}" />
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="relative min-h-screen overflow-x-hidden bg-base-200 text-base-content">
    <div class="drawer">
        <input id="workspace-drawer" type="checkbox" class="drawer-toggle">
        <div class="drawer-content min-h-screen">
            <x-header />

            <main class="min-[1430px]:w-[1430px] mx-auto mt-5">
                {{ $slot }}
            </main>

            <footer class="mt-8 text-center text-xs opacity-60 pb-4">
                {{ __('Integrated Accounting and Human Resources System') }} {{ __('Version :version', ['version' => config('app.version')]) }}
            </footer>
        </div>

        <div class="drawer-side z-50 xl:hidden">
            <label for="workspace-drawer" aria-label="{{ __('Close') }}" class="drawer-overlay"></label>
            <aside id="workspace-mobile-menu" class="flex min-h-full w-[min(18rem,calc(100vw-1rem))] flex-col overflow-y-auto bg-base-100 p-3 text-base-content shadow-2xl" aria-label="{{ __('Main navigation') }}">
                <div class="flex min-h-8 items-center justify-between border-b border-base-content/10 pb-2">
                    <span class="font-semibold text-sm md:text-base">{{ __(config('app.name')) }}</span>
                    <label for="workspace-drawer" class="btn btn-ghost btn-square btn-xs h-7 w-7" aria-label="{{ __('Close') }}">
                        <span aria-hidden="true" class="text-lg">&times;</span>
                    </label>
                </div>
                @can('access-super-admin-panel')
                    <a href="{{ route('management.dashboard') }}" class="btn btn-ghost mt-3 w-full justify-start text-sm font-medium">
                        {{ __('Admin panel') }}
                    </a>
                @endcan
                <ul class="app-mobile-menu menu mt-2 w-full gap-1 border-t border-base-content/10 text-sm" data-main-menu>
                    <x-menu />
                </ul>
            </aside>
        </div>
    </div>

    @stack('scripts')

    @stack('footer')
</body>

</html>
