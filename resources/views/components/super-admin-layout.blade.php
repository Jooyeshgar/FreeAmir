@props(['title' => __('Super-Admin Panel')])

@php
    $user = auth()->user();
    $hasCurrentWorkspace = $user->companies()->whereKey(getActiveCompany())->where('fiscal_year', toEnglish(jdate('Y')))->exists();
    $navigation = [
        [__('Dashboard'), route('management.dashboard'), request()->routeIs('management.dashboard'), 'M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z'],
        [__('Companies'), route('companies.index'), request()->routeIs('companies.*'), 'M3 21h18M6 21V7l6-4 6 4v14M9 10h1m4 0h1M9 14h1m4 0h1'],
        [__('Users'), route('users.index'), request()->routeIs('users.*'), 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        [__('Roles'), route('roles.index'), request()->routeIs('roles.*'), 'M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Zm-3 9 2 2 4-4'],
        [__('Permissions'), route('permissions.index'), request()->routeIs('permissions.*'), 'M15 7a4 4 0 1 0-7.9 1H3v4h3v3h3v-3h2.1A4 4 0 0 0 15 7Z'],
        [__('Activity log'), route('management.activity-logs.index'), request()->routeIs('management.activity-logs.*'), 'M4 19V9m5 10V5m5 14v-7m5 7V3'],
        [__('Settings'), route('management.settings'), request()->routeIs('management.settings'), 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19 12h2M3 12h2m7-9v2m0 14v2m6.36-2.64-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.42 1.42M7.05 16.95l-1.41 1.41'],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf_token" content="{{ csrf_token() }}">
    <script>try{document.documentElement.setAttribute('data-theme',localStorage.getItem('theme')==='dark'?'dark':'light')}catch(e){}</script>
    <title>{{ $title }} | {{ __(config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell min-h-screen overflow-x-hidden bg-[#f5f7f6] text-[#172033] antialiased dark:bg-slate-950 dark:text-slate-100" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    <aside id="management-sidebar" :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full ltr:-translate-x-full'" class="fixed inset-y-0 start-0 z-50 flex w-72 flex-col overflow-y-auto bg-[#15263b] text-slate-300 shadow-2xl transition-transform duration-300 lg:translate-x-0">
        <div class="grid-paper pointer-events-none absolute inset-0 opacity-70"></div>
        <div class="relative flex h-24 items-center gap-3 border-b border-white/10 px-6">
            <a href="{{ route('management.dashboard') }}" class="flex min-w-0 flex-1 items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#16a394] shadow-lg shadow-emerald-950/30"><img src="/images/logo.png" alt="" class="h-7 w-7 object-contain brightness-0 invert"></span>
                <span class="min-w-0"><strong class="block truncate text-xl font-extrabold text-white">{{ __(config('app.name')) }}</strong><span class="mt-0.5 block text-[11px] text-slate-400">{{ __('Super-Admin Panel') }}</span></span>
            </a>
            <button type="button" class="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-white/10 lg:hidden" @click="sidebarOpen=false" aria-label="{{ __('Close') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg></button>
        </div>
        <nav class="scrollbar relative flex-1 px-4 py-6" aria-label="{{ __('Super-Admin navigation') }}">
            <p class="mb-3 px-3 text-[10px] font-bold tracking-wider text-slate-500">{{ __('Management') }}</p>
            <ul class="space-y-1.5">
                @foreach ($navigation as [$label, $url, $active, $icon])
                    <li><a href="{{ $url }}" @click="sidebarOpen=false" @class(['relative mb-1 flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-medium transition before:absolute before:-start-4 before:h-7 before:w-1 before:rounded-e-full', 'bg-white/10 text-white before:bg-[#16a394]' => $active, 'text-slate-400 before:bg-transparent hover:bg-white/5 hover:text-white' => ! $active]) @if($active) aria-current="page" @endif>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg><span>{{ $label }}</span>
                    </a></li>
                @endforeach
            </ul>
        </nav>
        <div class="relative m-4 rounded-2xl border border-white/10 bg-white/5 p-4">
            <div class="mb-3 flex items-center justify-between text-xs"><span class="text-slate-400">{{ __('Service health') }}</span><span class="flex items-center gap-1.5 text-emerald-300"><i class="h-2 w-2 rounded-full bg-emerald-400"></i>{{ __('Stable') }}</span></div>
            <a href="{{ $hasCurrentWorkspace ? route('home') : route('management.dashboard') }}" class="flex items-center gap-3 border-t border-white/10 pt-3 transition">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="min-w-0 flex-1"><strong class="block truncate text-xs text-white">{{ $user->name }}</strong><span class="mt-1 block text-[10px] text-slate-500">{{ __('Go to workspace') }}</span></span>
                <svg class="h-4 w-4 text-slate-500 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6" /></svg>
            </a>
        </div>
    </aside>

    <div class="min-h-screen lg:ps-72">
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-[#f5f7f6]/90 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/90">
            <div class="flex h-20 items-center gap-3 px-4 sm:px-6 xl:px-8">
                <button type="button" class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 lg:hidden dark:border-slate-700" @click="sidebarOpen=true" aria-controls="management-sidebar" aria-label="{{ __('Menu') }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg></button>
                <div class="min-w-0 lg:w-48 lg:flex-none"><p class="text-[10px] text-slate-400">{{ __('Management') }} / {{ $title }}</p><h1 class="mt-0.5 truncate text-base font-bold text-slate-800 dark:text-white">{{ $title }}</h1></div>
                <form method="GET" action="{{ route('users.index') }}" role="search" class="mx-auto hidden w-full max-w-md lg:block">
                    <label class="relative block">
                        <span class="pointer-events-none absolute inset-y-0 start-3 grid place-items-center text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" stroke-width="1.8"/><path stroke-linecap="round" stroke-width="1.8" d="m20 20-4-4"/></svg></span>
                        <input type="search" name="search" value="{{ request()->routeIs('users.index') ? request('search') : '' }}" placeholder="{{ __('Search users, companies, and records...') }}" class="h-11 w-full rounded-2xl border border-slate-200 bg-white ps-10 pe-4 text-xs outline-none transition placeholder:text-slate-400 focus:border-[#16a394] focus:ring-4 focus:ring-[#16a394]/10 dark:border-slate-700 dark:bg-slate-800 dark:focus:border-[#16a394]">
                    </label>
                </form>
                <label class="swap swap-rotate grid h-10 w-10 cursor-pointer place-items-center rounded-xl border border-slate-200 text-slate-500 dark:border-slate-700" aria-label="{{ __('Dark mode') }}"><input type="checkbox" value="dark" class="theme-controller"><svg class="swap-off h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0-16v2m0 16v2M2 12h2m16 0h2" /></svg><svg class="swap-on h-5 w-5 fill-current" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" /></svg></label>
                <details class="dropdown dropdown-end"><summary class="flex h-10 cursor-pointer list-none items-center rounded-xl border border-slate-200 px-3 text-xs dark:border-slate-700">{{ app()->isLocale('fa') ? 'FA' : 'EN' }}</summary><div class="dropdown-content z-50 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">@foreach(['fa'=>__('Farsi'),'en'=>__('English')] as $locale=>$label)<form method="POST" action="{{ route('locale') }}">@csrf<input type="hidden" name="locale" value="{{ $locale }}"><button class="w-full rounded-lg px-3 py-2 text-start text-xs hover:bg-slate-100 dark:hover:bg-slate-800">{{ $label }}</button></form>@endforeach</div></details>
                <details class="dropdown dropdown-end"><summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl p-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"><span class="grid h-9 w-9 place-items-center rounded-xl bg-[#19a394] text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><span class="hidden max-w-28 truncate text-xs font-bold sm:block">{{ $user->name }}</span></summary><ul class="dropdown-content menu z-50 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 text-xs shadow-xl dark:border-slate-700 dark:bg-slate-900"><li class="menu-title"><span class="truncate font-normal text-slate-400">{{ $user->email }}</span></li><li><a href="{{ route('management.settings') }}">{{ __('Settings') }}</a></li><li><a href="{{ route('logout') }}" class="text-error">{{ __('Logout') }}</a></li></ul></details>
            </div>
            <x-impersonation-banner within-sticky-header />
        </header>
        <main class="relative mx-auto w-full max-w-[1600px] px-4 pb-7 pt-10 sm:px-7 sm:pt-12 xl:px-10 xl:pb-10">{{ $slot }}</main>
        <footer class="mx-auto flex w-full max-w-[1600px] justify-between border-t border-slate-200 px-4 py-5 text-[10px] text-slate-400 sm:px-6 xl:px-8 dark:border-slate-800"><span>{{ __(config('app.name')) }} · {{ __('Version') }} {{ localizeNumber(config('app.version')) }}</span><span>{{ __('Super-Admin Panel') }}</span></footer>
    </div>
</div>
@stack('scripts')
@stack('footer')
</body>
</html>
