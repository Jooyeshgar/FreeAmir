@props(['title' => __('Super-Admin Panel')])

@php
    $hasCurrentWorkspace = auth()->user()->companies()->whereKey(getActiveCompany())->where('fiscal_year', toEnglish(jdate('Y')))->exists();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf_token" content="{{ csrf_token() }}">
    <script>
        try {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <title>{{ $title }} | {{ __(config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="relative min-h-screen overflow-x-hidden bg-base-200 text-base-content"
    dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
    <div class="pointer-events-none fixed inset-x-0 top-0 h-96 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-32 end-8 h-80 w-80 rounded-full bg-emerald-200/25 blur-3xl dark:bg-emerald-900/10"></div>
    </div>

    <header class="sticky top-0 z-30 w-full border-b border-base-content/8 bg-base-100/90 backdrop-blur-md">
        <div class="h-1 bg-linear-to-r from-emerald-500 via-teal-500 to-cyan-500"></div>
        <div class="navbar mx-auto min-h-14 w-full max-w-[1430px] items-center justify-between gap-2 px-3">
            <nav class="flex min-w-0 flex-1 items-center gap-1" aria-label="{{ __('Super-Admin navigation') }}">
                <a href="{{ $hasCurrentWorkspace ? route('home') : route('management.dashboard') }}"
                    class="flex shrink-0 items-center rounded-lg p-1.5 transition-colors hover:bg-base-200"
                    aria-label="{{ __(config('app.name')) }}">
                    <img src="/images/logo.png" alt="{{ __(config('app.name')) }}" class="h-9 w-9 object-contain">
                </a>

                <ul class="app-main-menu menu menu-horizontal hidden flex-nowrap px-1 lg:flex" data-main-menu>
                    <li>
                        <a href="{{ route('management.dashboard') }}" @class([
                            'text-sm',
                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => request()->routeIs('management.dashboard'),
                        ])>{{ __('Dashboard') }}</a>
                    </li>
                    <li>
                        <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                            <summary @class([
                                'text-sm',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => request()->routeIs('companies.*', 'users.*'),
                            ])>{{ __('Organization') }}</summary>
                            <ul class="app-main-menu-panel z-50 mt-2 w-52">
                                <li><a href="{{ route('companies.index') }}" @class(['active' => request()->routeIs('companies.*')])>{{ __('Companies') }}</a></li>
                                <li><a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>{{ __('Users') }}</a></li>
                            </ul>
                        </details>
                    </li>
                    <li>
                        <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                            <summary @class([
                                'text-sm',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => request()->routeIs('roles.*', 'permissions.*'),
                            ])>{{ __('Access control') }}</summary>
                            <ul class="app-main-menu-panel z-50 mt-2 w-52">
                                <li><a href="{{ route('roles.index') }}" @class(['active' => request()->routeIs('roles.*')])>{{ __('Roles') }}</a></li>
                                <li><a href="{{ route('permissions.index') }}" @class(['active' => request()->routeIs('permissions.*')])>{{ __('Permissions') }}</a></li>
                            </ul>
                        </details>
                    </li>
                    <li>
                        <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                            <summary @class([
                                'text-sm',
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300' => request()->routeIs('management.settings', 'management.activity-logs.*'),
                            ])>{{ __('System') }}</summary>
                            <ul class="app-main-menu-panel z-50 mt-2 w-52">
                                <li><a href="{{ route('management.activity-logs.index') }}" @class(['active' => request()->routeIs('management.activity-logs.*')])>{{ __('Activity log') }}</a></li>
                                <li><a href="{{ route('management.settings') }}" @class(['active' => request()->routeIs('management.settings')])>{{ __('Settings') }}</a></li>
                            </ul>
                        </details>
                    </li>
                </ul>
            </nav>

            <nav class="hidden lg:block" aria-label="{{ __('User menu') }}">
                <ul class="app-main-menu menu menu-horizontal flex-nowrap px-1" data-main-menu>
                    <li class="!flex flex-row items-center gap-1">
                        <a href="{{ route('home') }}" class="btn btn-ghost btn-sm">
                            {{ __('Workspace') }}
                        </a>
                        <label class="swap swap-rotate btn btn-ghost btn-square btn-sm" aria-label="{{ __('Dark mode') }}">
                            <input type="checkbox" value="dark" class="theme-controller">
                            <svg class="swap-off h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0-16v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" /></svg>
                            <svg class="swap-on h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg>
                        </label>
                        <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                            <summary class="text-sm">
                                {{ app()->isLocale('fa') ? __('Farsi') : __('English') }}
                            </summary>
                            <form id="management-locale-fa-form" method="POST" action="{{ route('locale') }}" class="hidden">
                                @csrf
                                <input type="hidden" name="locale" value="fa">
                            </form>
                            <form id="management-locale-en-form" method="POST" action="{{ route('locale') }}" class="hidden">
                                @csrf
                                <input type="hidden" name="locale" value="en">
                            </form>
                            <ul class="app-main-menu-panel z-50 mt-2 w-40">
                                <li><button type="submit" form="management-locale-fa-form" lang="fa">{{ __('Farsi') }}</button></li>
                                <li><button type="submit" form="management-locale-en-form" lang="en">{{ __('English') }}</button></li>
                            </ul>
                        </details>
                        <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                            <summary class="gap-2 text-sm">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-linear-to-br from-emerald-500 to-teal-600 font-bold text-white shadow-sm">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                                <span class="hidden max-w-32 truncate sm:block">{{ auth()->user()->name }}</span>
                            </summary>
                            <ul class="app-main-menu-panel z-50 mt-2 w-60">
                                <li class="menu-title"><span class="truncate">{{ auth()->user()->email }}</span></li>
                                <li><a href="{{ route('management.settings') }}">{{ __('Settings') }}</a></li>
                                <li><a href="{{ route('logout') }}" class="text-error">{{ __('Logout') }}</a></li>
                            </ul>
                        </details>
                    </li>
                </ul>
            </nav>

            <nav class="ms-auto flex items-center gap-1 lg:hidden" aria-label="{{ __('Mobile navigation') }}">
                <label class="swap swap-rotate btn btn-ghost btn-square min-h-11 min-w-11" aria-label="{{ __('Dark mode') }}">
                    <input type="checkbox" value="dark" class="theme-controller">
                    <svg class="swap-off h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24" stroke-width="2"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0-16v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" /></svg>
                    <svg class="swap-on h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg>
                </label>
                <details class="dropdown dropdown-end" data-mobile-menu>
                    <summary class="btn btn-ghost btn-square min-h-11 min-w-11" aria-label="{{ __('Super-Admin navigation') }}">
                        <svg class="h-6 w-6 fill-none stroke-current" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </summary>
                    <ul class="dropdown-content menu z-50 mt-2 max-h-[calc(100dvh-5rem)] w-[min(20rem,calc(100vw-1.5rem))] overflow-y-auto rounded-box border border-base-300 bg-base-100 p-2 shadow-xl">
                        <li><a href="{{ route('management.dashboard') }}">{{ __('Dashboard') }}</a></li>
                        <li class="menu-title"><span>{{ __('Organization') }}</span></li>
                        <li><a href="{{ route('companies.index') }}">{{ __('Companies') }}</a></li>
                        <li><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
                        <li class="menu-title"><span>{{ __('Access control') }}</span></li>
                        <li><a href="{{ route('roles.index') }}">{{ __('Roles') }}</a></li>
                        <li><a href="{{ route('permissions.index') }}">{{ __('Permissions') }}</a></li>
                        <li class="menu-title"><span>{{ __('System') }}</span></li>
                        <li><a href="{{ route('management.activity-logs.index') }}">{{ __('Activity log') }}</a></li>
                        <li><a href="{{ route('management.settings') }}">{{ __('Settings') }}</a></li>
                        <li><a href="{{ route('home') }}">{{ __('Workspace') }}</a></li>
                        <li><a href="{{ route('logout') }}" class="text-error">{{ __('Logout') }}</a></li>
                    </ul>
                </details>
            </nav>
        </div>

        <x-impersonation-banner within-sticky-header />
    </header>

    <main class="relative mx-auto mt-3 w-full max-w-[1430px] px-3 sm:mt-5 sm:px-4 lg:px-5">
        {{ $slot }}
    </main>

    <footer class="mt-8 pb-4 text-center text-xs opacity-60">
        {{ __(config('app.name')) }} - {{ __('Version') }} {{ localizeNumber(config('app.version')) }}
    </footer>

    @stack('scripts')
    @stack('footer')
</body>

</html>
