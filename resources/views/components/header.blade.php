<header class="sticky top-0 z-30 w-full border-b border-base-content/8 bg-base-100/90 backdrop-blur-md">
    <div class="navbar mx-auto min-h-14 w-full max-w-[1430px] items-center justify-between gap-2 px-3">
        <nav class="hidden min-w-0 flex-1 items-center gap-1 xl:flex" aria-label="{{ __('Main navigation') }}">
            <a href="/" class="flex shrink-0 items-center rounded-lg p-1.5 transition-colors hover:bg-base-200" aria-label="{{ config('app.name') }}">
                <img src="/images/logo.png" alt="Logo" class="h-9 w-9 object-contain">
            </a>
            <ul class="app-main-menu menu menu-horizontal flex-nowrap px-1" data-main-menu>
                <x-menu />
            </ul>
        </nav>

        <a href="/" class="flex shrink-0 items-center rounded-lg p-1.5 transition-colors hover:bg-base-200 xl:hidden"
            aria-label="{{ config('app.name') }}">
            <img src="/images/logo.png" alt="Logo" class="h-9 w-9 object-contain">
        </a>

        <nav class="hidden xl:block" aria-label="{{ __('User menu') }}">
            <ul class="app-main-menu flex shrink-0 items-center menu menu-horizontal px-1" data-main-menu>
                @can('access-super-admin-panel')
                    <li>
                        <a href="{{ route('management.dashboard') }}" class="btn btn-ghost btn-sm">
                            {{ __('Admin panel') }}
                        </a>
                    </li>
                @endcan
                <li>
                    <label class="swap swap-rotate btn btn-ghost btn-square btn-sm" aria-label="{{ __('Dark mode') }}">
                        <input type="checkbox" value="dark" class="theme-controller" />

                        <svg class="swap-off h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path
                                d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z" />
                        </svg>

                        <svg class="swap-on h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path
                                d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z" />
                        </svg>
                    </label>
                </li>
                <li>
                    <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                        <summary class="text-sm">
                            {{ app()->isLocale('fa') ? __('Farsi') : __('English') }}
                        </summary>
                        <form id="locale-fa-form" method="POST" action="{{ route('locale') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="locale" value="fa">
                        </form>
                        <form id="locale-en-form" method="POST" action="{{ route('locale') }}" class="hidden">
                            @csrf
                            <input type="hidden" name="locale" value="en">
                        </form>
                        <ul class="app-main-menu-panel z-50 w-52">
                            <li>
                                <button type="submit" form="locale-fa-form" lang="fa">{{ __('Farsi') }}</button>
                            </li>
                            <li>
                                <button type="submit" form="locale-en-form" lang="en">{{ __('English') }}</button>
                            </li>
                        </ul>
                    </details>
                </li>
                <li>
                    <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                        <summary class="text-sm">
                            {{ cookie('active-company-id') ? config('active-company-name') . ' - ' . config('active-company-fiscal-year') : __('Please Select a Company') }}
                        </summary>
                        <ul class="app-main-menu-panel z-50 w-52">
                            @foreach (auth()->user()->companies as $company)
                                <li>
                                    <a href="{{ route('change-company', ['company' => $company]) }}">
                                        {{ $company->name . ' - ' . $company->fiscal_year }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                </li>
                <li>
                    <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                        <summary class="font-medium text-sm">{{ Auth::user()->name }}</summary>
                        <ul class="app-main-menu-panel z-50 w-52">
                            @can('api-tokens.index')
                                <li><a href="{{ route('api-tokens.index') }}">{{ __('API Tokens') }}</a></li>
                            @endcan
                            @if (Auth::user()->employee && Auth::user()->can('employee-portal.dashboard'))
                                <li><a href="{{ route('employee-portal.employee.show') }}">{{ __('My Information') }}</a></li>
                            @endif
                            <li><a href="/logout">{{ __('Logout') }}</a></li>
                        </ul>
                    </details>
                </li>
            </ul>
        </nav>

        <nav class="ms-auto flex items-center gap-1 xl:hidden" aria-label="{{ __('Mobile navigation') }}">
            <label class="swap swap-rotate btn btn-ghost btn-square min-h-11 min-w-11" aria-label="{{ __('Dark mode') }}">
                <input type="checkbox" value="dark" class="theme-controller" />
                <svg class="swap-off h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12Zm0-16v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
                </svg>
                <svg class="swap-on h-5 w-5 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
                </svg>
            </label>

            <details class="dropdown dropdown-end" data-mobile-menu>
                <summary class="btn btn-ghost btn-square min-h-11 min-w-11" aria-label="{{ __('Main navigation') }}">
                    <svg class="h-6 w-6 fill-none stroke-current" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </summary>
                <div class="dropdown-content z-50 mt-2 max-h-[calc(100dvh-5rem)] w-[min(22rem,calc(100vw-1.5rem))] overflow-y-auto rounded-box border border-base-300 bg-base-100 p-2 shadow-xl">
                    <ul class="mobile-main-menu menu w-full p-0" data-main-menu>
                        <x-menu />
                    </ul>
                    <div class="divider my-2"></div>
                    <ul class="menu w-full p-0">
                        @can('access-super-admin-panel')
                            <li><a href="{{ route('management.dashboard') }}">{{ __('Admin panel') }}</a></li>
                        @endcan
                        <li class="menu-title"><span>{{ __('Company') }}</span></li>
                        @foreach (auth()->user()->companies as $company)
                            <li><a href="{{ route('change-company', ['company' => $company]) }}">{{ $company->name . ' - ' . $company->fiscal_year }}</a></li>
                        @endforeach
                        <li class="menu-title"><span>{{ __('Language') }}</span></li>
                        <li><form method="POST" action="{{ route('locale') }}">@csrf<input type="hidden" name="locale" value="fa"><button type="submit" lang="fa">{{ __('Farsi') }}</button></form></li>
                        <li><form method="POST" action="{{ route('locale') }}">@csrf<input type="hidden" name="locale" value="en"><button type="submit" lang="en">{{ __('English') }}</button></form></li>
                        <li class="menu-title"><span class="truncate">{{ Auth::user()->name }}</span></li>
                        @can('api-tokens.index')
                            <li><a href="{{ route('api-tokens.index') }}">{{ __('API Tokens') }}</a></li>
                        @endcan
                        @if (Auth::user()->employee && Auth::user()->can('employee-portal.dashboard'))
                            <li><a href="{{ route('employee-portal.employee.show') }}">{{ __('My Information') }}</a></li>
                        @endif
                        <li><a href="/logout" class="text-error">{{ __('Logout') }}</a></li>
                    </ul>
                </div>
            </details>
        </nav>
    </div>

    <x-impersonation-banner within-sticky-header />
</header>
