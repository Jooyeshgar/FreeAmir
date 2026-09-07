<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg flex flex-1 flex-col overflow-hidden bg-cover bg-center sm:rounded-t-3xl sm:border-8 sm:border-gray-200 sm:border-opacity-85">
        <div class="flex items-center justify-center px-3 py-6 sm:px-6 sm:py-10 md:py-14">
            <div class="card w-full max-w-sm bg-white p-4 shadow-xl sm:p-7">
                <form method="POST" action="{{ route('registered-user.company.store') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Create your company') }}</h1>
                    <p class="text-sm mt-2">{{ __('Your default accounting subjects will be created automatically.') }}</p>
                    <x-show-message-bags />

                    <x-text-input class="mx-auto mt-1 w-full max-w-xs" input_name="name" id="name" title="{{ __('Company name') }}" placeHolder="{{ __('Enter your company\'s name') }}" :input_value="old('name')" />
                    <x-text-input class="mx-auto mt-1 w-full max-w-xs" input_name="fiscal_year" id="fiscal_year" title="{{ __('Fiscal year') }}" placeHolder="{{ __('Enter your company\'s fiscal year') }}" :input_value="old('fiscal_year', toEnglish(jdate('Y')))" />
                    <x-text-input class="mx-auto mt-1 w-full max-w-xs" input_name="currency" id="currency" title="{{ __('Currency') }}" placeHolder="{{ __('Enter your company\'s currency') }}" :input_value="old('currency', 'Rial')" />
                    <x-text-input class="mx-auto mt-1 w-full max-w-xs" input_name="phone_number" id="phone_number" title="{{ __('Phone number') }}" placeHolder="{{ __('Enter your company\'s phone number') }}" :input_value="old('phone_number')" />

                    <div class="mx-auto mt-4 flex w-full max-w-xs flex-col gap-2 min-[375px]:flex-row min-[375px]:items-center min-[375px]:gap-1">
                        <button type="submit" class="btn bg-blue-500 text-white hover:bg-blue-600 min-[375px]:w-[70%] min-[375px]:shrink-0">{{ __('Create') }}</button>
                        <a href="{{ route('login') }}" class="btn whitespace-nowrap bg-gray-300 text-black hover:bg-gray-400 min-[375px]:min-w-0 min-[375px]:flex-1 min-[375px]:px-1 min-[375px]:text-xs">{{ __('Back to Login') }}</a>
                    </div>
                </form>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
