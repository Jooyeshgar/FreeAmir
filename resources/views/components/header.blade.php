<header class="sticky top-0 z-30 w-full border-b border-base-content/8 bg-base-100/90 backdrop-blur-md">
    <div class="navbar w-full max-w-full min-h-14 items-center justify-between gap-2 px-3 min-[1430px]:mx-auto min-[1430px]:w-[1430px]">
        <div class="flex shrink-0 items-center gap-1" dir="ltr">
            <button type="button" @click="mobileMenuOpen = true" class="btn btn-ghost btn-square btn-xs h-7 w-7 xl:hidden" aria-label="{{ __('Menu') }}" aria-controls="workspace-mobile-menu" :aria-expanded="mobileMenuOpen.toString()">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6.5h16M4 12h16M4 17.5h16" />
                </svg>
            </button>
            <a href="/" class="flex shrink-0 items-center rounded-lg p-1 transition-colors hover:bg-base-200" aria-label="{{ config('app.name') }}">
                <img src="/images/logo.png" alt="Logo" class="h-9 w-9 object-contain">
            </a>
        </div>

        <nav class="flex min-w-0 flex-1 items-center gap-1" aria-label="{{ __('Main navigation') }}">
            <ul class="app-main-menu menu hidden px-1 xl:menu-horizontal xl:flex-nowrap xl:flex" data-main-menu>
                <x-menu />
            </ul>
        </nav>

        <nav aria-label="{{ __('User menu') }}">
            <ul class="app-main-menu flex shrink-0 flex-nowrap items-center menu menu-horizontal gap-0.5 px-1" data-main-menu>
                <li>
                    <label class="swap swap-rotate btn btn-ghost btn-square btn-sm" aria-label="{{ __('Dark mode') }}">
                        <input type="checkbox" value="dark" class="theme-controller" />

                        <svg class="swap-off h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z" />
                        </svg>

                        <svg class="swap-on h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z" />
                        </svg>
                    </label>
                </li>
                <li>
                    <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                        <summary class="text-sm">
                            <span class="hidden md:inline">{{ app()->isLocale('fa') ? __('Farsi') : __('English') }}</span>
                            <svg class="h-4 w-4 shrink-0 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v18m9-9H3m15.5-6.5a9 9 0 1 0 0 13M8 4.5a9 9 0 0 0 0 15" />
                            </svg>
                        </summary>
                        <form id="locale-fa-form" method="POST" action="{{ route('locale') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="locale" value="fa">
                        </form>
                        <form id="locale-en-form" method="POST" action="{{ route('locale') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="locale" value="en">
                        </form>
                        <ul class="app-main-menu-panel z-50 w-auto md:w-max min-w-full max-w-[calc(100vw-2rem)] ltr:right-0 rtl:left-0 text-sm">
                            <li>
                                <button type="submit" form="locale-fa-form" lang="fa" class="whitespace-nowrap">{{ __('Farsi') }}</button>
                            </li>
                            <li>
                                <button type="submit" form="locale-en-form" lang="en" class="whitespace-nowrap">{{ __('English') }}</button>
                            </li>
                        </ul>
                    </details>
                </li>
                <li>
                    <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                        <summary class="text-sm">
                            <span class="hidden md:inline">{{ cookie('active-company-id') ? config('active-company-name') . ' - ' . config('active-company-fiscal-year') : __('Please Select a Company') }}</span>
                            <svg class="h-4 w-4 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m3 0h1m-4-8h1m-1-4h1m4 4h1m-1-4h1M9 3v18" />
                            </svg>
                        </summary>
                        <ul class="app-main-menu-panel z-50 w-auto md:w-max min-w-full max-w-[calc(100vw-2rem)] ltr:right-0 rtl:left-0 text-sm">
                            @foreach (auth()->user()->companies as $company)
                                <li>
                                    <a href="{{ route('change-company', ['company' => $company]) }}" class="whitespace-nowrap">
                                        {{ $company->name . ' - ' . $company->fiscal_year }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                </li>
                <li>
                    <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                        <summary class="font-medium text-sm">
                            <span class="hidden md:inline">{{ auth()->user()->name }}</span>
                            <svg class="h-4 w-4 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" />
                            </svg>
                        </summary>
                        <ul class="app-main-menu-panel z-50 w-auto md:w-max min-w-full max-w-[calc(100vw-2rem)] ltr:right-0 rtl:left-0 text-sm">
                            @can('api-tokens.index')
                                <li><a href="{{ route('api-tokens.index') }}" class="whitespace-nowrap">{{ __('API Tokens') }}</a></li>
                            @endcan
                            @if (auth()->user()->employee && auth()->user()->can('employee-portal.dashboard'))
                                <li><a href="{{ route('employee-portal.employee.show') }}" class="whitespace-nowrap">{{ __('My Information') }}</a></li>
                            @endif
                            <li><a href="/logout" class="whitespace-nowrap">{{ __('Logout') }}</a></li>
                        </ul>
                    </details>
                </li>
            </ul>
        </nav>
    </div>

    <div x-cloak x-show="mobileMenuOpen" x-transition.opacity @click="mobileMenuOpen = false"></div>
    <aside id="workspace-mobile-menu" dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" x-cloak x-show="mobileMenuOpen" x-transition:enter="transition-transform duration-200" x-transition:enter-start="ltr:-translate-x-full rtl:translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition-transform duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="ltr:-translate-x-full rtl:translate-x-full" @click.stop
        class="fixed inset-x-0 top-16 z-[1000] flex h-[calc(100dvh-3.5rem)] max-h-[calc(100dvh-3.5rem)] w-[min(22rem,100vw)] ltr:left-0 rtl:right-0 flex-col overflow-y-auto overscroll-contain bg-base-100 p-3 shadow-2xl max-md:inset-0 max-md:h-dvh max-md:max-h-dvh max-md:w-screen xl:hidden"
        aria-label="{{ __('Main navigation') }}">
        <div class="flex min-h-8 items-center justify-between border-b border-base-content/10 pb-2">
            <span class="font-semibold text-sm md:text-base">{{ __(config('app.name')) }}</span>
            <button type="button" @click="mobileMenuOpen = false" class="btn btn-ghost btn-square btn-xs h-7 w-7" aria-label="{{ __('Close') }}">
                <span aria-hidden="true" class="text-lg">&times;</span>
            </button>
        </div>
        @can('access-super-admin-panel')
            <a href="{{ route('management.dashboard') }}" @click="mobileMenuOpen = false" class="btn btn-ghost mt-3 w-full justify-start text-sm font-medium">
                {{ __('Admin panel') }}
            </a>
        @endcan
        <ul @click="if ($event.target.closest('a')) mobileMenuOpen = false" class="app-mobile-menu menu mt-2 w-full gap-1 text-sm border-t border-base-content/10" data-main-menu>
            <x-menu />
        </ul>
    </aside>

    <x-impersonation-banner within-sticky-header />
</header>
