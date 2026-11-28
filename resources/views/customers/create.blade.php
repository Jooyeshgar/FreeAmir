<x-app-layout :title="__('Create Customer')">
    <div class="card-title">{{ __('Create Customer') }}</div>
    <form action="{{ route('customers.store') }}" method="POST" class="relative">
        <div class="card bg-base-100 shadow-xl rounded-xl">

            @csrf
            @if ($returnTo = \App\Services\OnboardingService::safeReturnUrl(old('return_to', request('return_to'))))
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif
            <div class="card-body p-4  ">

                <x-show-message-bags />
                @if ($returnTo)
                    <div class="alert alert-info alert-soft mb-2 text-xs">
                        <span>{{ __('After saving, you will be redirected back to the previous page.') }}</span>
                    </div>
                @endif

                @include('customers.form')
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        {{ __('Create') }}
                    </button>
                </div>
            </div>
        </div>

    </form>
</x-app-layout>
