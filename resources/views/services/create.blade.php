<x-app-layout :title="__('Create Service')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            @if ($returnTo = \App\Services\OnboardingService::safeReturnUrl(old('return_to', request('return_to'))))
                <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @endif
            <div class="card-body">
                <h2 class="card-title">{{ __('Add service') }}</h2>
                <x-show-message-bags />
                @if ($returnTo)
                    <div class="alert alert-info alert-soft mb-2 text-xs">
                        <span>{{ __('After saving, you will be redirected back to the previous page.') }}</span>
                    </div>
                @endif

                @include('services.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>

</x-app-layout>
