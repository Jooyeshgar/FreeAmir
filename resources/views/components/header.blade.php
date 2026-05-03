<header class="navbar justify-between">
    <div>
        <div class="flex items-center bg-base-200 rounded-xl mx-4 p-1">
            <img src="/images/logo.png" alt="Logo" width="50">
        </div>
        <ul class="menu menu-horizontal px-1 bg-base-200 rounded-xl">
            <x-menu />
        </ul>
    </div>

    <div>
        <ul class="menu menu-horizontal px-1 bg-base-200 rounded-xl">
            <li>
                <label class="flex cursor-pointer gap-2 px-3 py-2">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5" />
                        <path
                            d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" />
                    </svg>
                    <input type="checkbox" value="dark" class="toggle theme-controller" aria-label="{{ __('Dark mode') }}" />
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </label>
            </li>
            <li class="dropdown dropdown-end dropdown-hover">
                <div tabindex="0" role="button">
                    {{ cookie('active-company-id') ? config('active-company-name') . ' - ' . config('active-company-fiscal-year') : __('Please Select a Company') }}
                </div>
                <ul tabindex="0" class="dropdown-end dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
                    @foreach (auth()->user()->companies as $company)
                        <li>
                            <a href="{{ route('change-company', ['company' => $company]) }}">
                                {{ $company->name . ' - ' . $company->fiscal_year }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
            <li class="dropdown dropdown-end dropdown-hover">
                <div tabindex="1" role="button">{{ Auth::user()->name }}</div>
                <ul tabindex="1" class="dropdown dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
                    <li><a href="/logout">{{ __('Logout') }}</a></li>
                </ul>
            </li>
        </ul>
    </div>
</header>
