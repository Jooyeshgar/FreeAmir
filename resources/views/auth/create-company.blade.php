<x-login-layout>
    @include('auth.partials.header')

    <div class="login-bg bg-cover bg-center rounded-t-3xl flex-1 border-8 border-gray-200 border-opacity-85 overflow-hidden">
        <div class="flex items-center justify-center rounded-3xl">
            <div class="card w-96 p-7 mt-16 bg-white">
                <form method="POST" action="{{ route('registered-user.company.store') }}">
                    @csrf
                    <h1 class="font-bold text-center">{{ __('Create your company') }}</h1>
                    <p class="text-sm mt-2">{{ __('Your default accounting subjects will be created automatically.') }}</p>
                    <x-show-message-bags />

                    <x-text-input class="mt-1 w-full" input_name="name" id="name" title="{{ __('Company name') }}" placeHolder="{{ __('Enter your company\'s name') }}" :input_value="old('name')" />
                    <x-text-input class="mt-1 w-full" input_name="fiscal_year" id="fiscal_year" title="{{ __('Fiscal year') }}" placeHolder="{{ __('Enter your company\'s fiscal year') }}" :input_value="old('fiscal_year', toEnglish(jdate('Y')))" />
                    <x-text-input class="mt-1 w-full" input_name="currency" id="currency" title="{{ __('Currency') }}" placeHolder="{{ __('Enter your company\'s currency') }}" :input_value="old('currency', 'Rial')" />
                    <x-text-input class="mt-1 w-full" input_name="phone_number" id="phone_number" title="{{ __('Phone number') }}" placeHolder="{{ __('Enter your company\'s phone number') }}" :input_value="old('phone_number')" />

                    <div class="flex items-center justify-between mt-4">
                        <button type="submit" class="btn bg-blue-500 hover:bg-blue-600 text-white">{{ __('Create') }}</button>
                        <a href="{{ route('login') }}" class="btn bg-gray-300 hover:bg-gray-400 text-black">{{ __('Back to Login') }}</a>
                    </div>
                </form>
            </div>
        </div>
        @include('auth.partials.footer')
    </div>
</x-login-layout>
