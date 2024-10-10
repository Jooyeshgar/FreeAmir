<header class="navbar justify-between">
    <div class="flex-2 px-2 lg:flex-none">
        <div class="flex items-center bg-gray-200 rounded-xl mx-4 p-1">
            <img src="/images/logo.png" alt="Logo" width="50" class="">
        </div>
        <ul class="menu menu-horizontal px-1 bg-gray-200 rounded-xl">
            <x-menu />
        </ul>
    </div>

    <div class="flex flex-1 justify-end px-2">
        <ul class="menu menu-horizontal px-1 bg-gray-200 rounded-xl">
            <li class="dropdown dropdown-hover">
                <div tabindex="0" role="button">
                    {{ session('active-company-id') ? session('active-company-name') . ' - ' . session('active-company-fiscal-year') : __('Please Select a Company') }}
                </div>
                <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
                    @foreach (auth()->user()->companies as $company)
                        <li>
                            <a href="{{ route('change-company', ['company' => $company->id]) }}">
                                {{ $company->name . ' - ' . $company->fiscal_year }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
            <li class="dropdown dropdown-hover">
                <div tabindex="1" role="button">{{ Auth::user()->name }}</div>
                <ul tabindex="1" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
                    <li><a href="/logout">{{ __('Logout') }}</a></li>
                </ul>
            </li>
        </ul>
    </div>
</header>
