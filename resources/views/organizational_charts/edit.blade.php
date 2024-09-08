<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Organizational Chart') }}
        </h2>
    </x-slot>
    <div class="card-title">{{ __('Edit Organizational Chart') }}</div>
    <form action="{{ route('payroll.organizational_charts.update', $organizationalChart) }}" method="POST" class="relative">
        @method('PUT')
        @csrf
        <div class="card bg-gray-100 shadow-xl rounded-xl">

            <div class="card-body p-4">

                <x-show-message-bags />

                @include('organizational_charts.form')

            </div>

        </div>
        <div class="mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Update') }}
            </button>
        </div>
    </form>
</x-app-layout>
