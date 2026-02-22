<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Public Holiday') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('public-holidays.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Public Holiday') }}</h2>
                <x-show-message-bags />

                @include('public-holidays.form')

                <div class="card-actions justify-end">
                    <a href="{{ route('public-holidays.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
