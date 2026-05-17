<header class="navbar items-center justify-between gap-4">
    <nav class="flex min-w-0 flex-1 items-center gap-2" aria-label="{{ __('Main navigation') }}">
        <a href="/" class="flex shrink-0 items-center rounded-xl bg-base-200 p-1" aria-label="{{ config('app.name') }}">
            <img src="/images/logo.png" alt="Logo" class="h-12 w-12 object-contain">
        </a>
        <ul class="app-main-menu menu px-1 lg:menu-horizontal lg:flex-nowrap bg-base-200 rounded-xl" data-main-menu>
            <x-menu />
        </ul>
    </nav>

    <nav aria-label="{{ __('User menu') }}">
        <ul class="app-main-menu flex shrink-0 items-center menu menu-horizontal px-1 bg-base-200 rounded-xl" data-main-menu>
            <li>
                <label class="flex cursor-pointer gap-2 px-3 py-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5" />
                        <path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" />
                    </svg>
                    <input type="checkbox" value="dark" class="toggle theme-controller" aria-label="{{ __('Dark mode') }}" />
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </label>
            </li>
            <li>
                <details class="app-main-menu-dropdown" data-main-menu-dropdown>
                    <summary>
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
                    <summary>{{ Auth::user()->name }}</summary>
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
</header>
