    <div class="w-1/2 max-[850px]:w-full bg-[#E9ECEF] rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">{{ __('User Details') }}</h2>
        </div>
        <div class="border-b-2 border-b-[#CED4DA] m-2">
            <div class="flex text-[#212529] mt-4 max-[850px]:mb-4">
                <div class="w-1/2 ms-4 max-[850px]:text-xs">
                    <span class="text-[#495057]">{{ __('Name') }}:</span>
                    {{ auth()->user()->name }}
                </div>

                <div class="w-1/2 ms-4 mb-4 max-[850px]:text-xs">
                    <span class="text-[#495057]">{{ __('Email') }}:</span>
                    {{ auth()->user()->email }}
                </div>
            </div>

            <div class="flex text-[#212529] mt-1 max-[850px]:mb-4">
                <div class="w-1/2 ms-4 mb-4 max-[850px]:text-xs">
                    <span class="text-[#495057]">{{ __('Companies') }}:</span>
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
