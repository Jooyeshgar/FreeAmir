    <div class="home-card w-1/3 max-[850px]:w-full">
        <div class="home-card-header">
            <h2 class="home-card-title">{{ __('User Details') }}</h2>
        </div>
        <div class="home-card-body m-2 border-b border-b-slate-200 dark:border-b-slate-700">
            <div class="flex mt-4 max-[850px]:mb-4">
                <div class="w-1/2 ms-4 max-[850px]:text-xs">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Name') }}:</span>
                    {{ auth()->user()->name }}
                </div>

                <div class="w-1/2 ms-4 mb-4 max-[850px]:text-xs">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Email') }}:</span>
                    {{ auth()->user()->email }}
                </div>
            </div>

            <div class="flex mt-1 max-[850px]:mb-4">
                <div class="w-1/2 ms-4 mb-4 max-[850px]:text-xs">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Companies') }}:</span>
                    @foreach (auth()->user()->companies as $company)
                        <a href="{{ route('change-company', ['company' => $company->id]) }}">
                            {{ $company->name . ' - ' . $company->fiscal_year }}
                        </a>
                        @if (!$loop->last)
                            ,
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
