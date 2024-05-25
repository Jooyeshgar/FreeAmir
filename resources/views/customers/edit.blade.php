<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Customer') }}
        </h2>
    </x-slot>
    <div class="card-title">{{ __('Add customer') }}</div>
    <form action="{{ route('customers.update', $customer) }}" method="POST" class="relative">
        <div class="card bg-gray-100 shadow-xl rounded-xl " style="height: 440px">

            @csrf
            <div class="card-body p-4 ">

                <x-show-message-bags/>

                @include('customers.form')

            </div>

        </div>
        <div class="fixed bottom-0 mt-40 left-0  mb-4  ml-16 ">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Edit') }}
            </button>
        </div>
    </form>
</x-app-layout>
