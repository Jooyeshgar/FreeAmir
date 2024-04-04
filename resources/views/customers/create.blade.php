<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Customer') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('customers.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Add customer') }}</div>
                <x-show-message-bags />

                @include('customers.form')
                <div class="card-actions">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>