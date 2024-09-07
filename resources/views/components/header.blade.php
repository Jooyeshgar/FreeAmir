<div class="navbar flex justify-between">
    <div class="flex-none">
        <div class="flex items-center bg-gray-200 rounded-xl mx-4 p-1">
            <img src="/images/logo.png" alt="Logo" width="50" class="">
        </div>
        <ul class="menu menu-horizontal px-1 z-30 bg-gray-200 rounded-xl">
            <x-menu />
        </ul>
    </div>

    <div class="text-right">
        <ul class="menu menu-horizontal px-1 z-30 bg-gray-200 rounded-xl">
            <li>
                <details>
                    <summary>
                        {{ session('active-company-id') ? session('active-company-name') . ' - ' . session('active-company-fiscal-year') : __('Please Select a Company') }}
                    </summary>
                    <ul>
                        @foreach (auth()->user()->companies as $company)
                            <li>
                                <a href="{{ route('change-company', ['company' => $company->id]) }}">
                                    {{ $company->name . ' - ' . $company->fiscal_year }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </li>
            <li>
                <details>
                    <summary>{{ Auth::user()->name }}</summary>
                    <ul>
                        <li><a href="/logout">{{ __('Logout') }}</a></li>
                    </ul>
                </details>
            </li>
        </ul>
    </div>
</div>