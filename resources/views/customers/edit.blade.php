<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Customers') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('customers.update', $customer) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit customer') }}</div>
                <x-show-message-bags />

                @include('customers.form')
                <div class="card-actions">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
        </form>
    </div>
</x-app-layout>