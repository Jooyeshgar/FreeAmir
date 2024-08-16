@php
    $title = session('app.company') ? 
        session('app.company') . ' - ' . session('app.fiscal_year') :
        __('Please Select a Company');
@endphp

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
                    <summary>{{ $title }}</summary>
                    <ul>
                        @foreach (auth()->user()->companies->groupBy('name') as $name => $company)
                            <li>
                                <details>
                                    <summary>{{ $name }}</summary>
                                    <ul>
                                        @foreach ($company as $c)
                                            <li><a href="{{ route('change-company', ['company' => $name, 'company_id' => $c->id, 'year' => $c->pivot->fiscal_year]) }}">{{ $c->pivot->fiscal_year }}</a></li>
                                        @endforeach
                                    </ul>
                                </details>
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
