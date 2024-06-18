<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('General Journal Report') }}
        </h2>
    </x-slot>
    <div class="font-bold text-gray-600 py-6 text-2xl">
        <span>
            {{ __('General Journal Report') }}
        </span>
    </div>
    @include('reports.form', ['type' => 'Ledger'])
</x-app-layout>
