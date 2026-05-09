<x-app-layout :title="__('Edit customer')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit customer') }}
        </h2>
    </x-slot>
    <div class="card-title">{{ __('Edit customer') }}</div>
    <form action="{{ route('customers.update', $customer) }}" method="POST" class="relative">
        <div class="card bg-base-100 shadow-xl rounded-xl">

            @csrf
            @method('PUT')
            <div class="card-body p-4 ">

                <x-show-message-bags />

                @include('customers.form')
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        {{ __('Edit') }}
                    </button>
                </div>
            </div>
        </div>

    </form>
</x-app-layout>
