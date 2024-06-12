<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Transaction') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            ثبت سند حسابداری
        </span>
    </div>
    <div class="">

        <form action="{{ route('transactions.update',$document) }}" method="POST">
            <x-show-message-bags />
            @csrf
            @method('PUT')

            @include('transactions.form')



        </form>
    </div>
</x-app-layout>
