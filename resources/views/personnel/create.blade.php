<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Personnel Record') }}
        </h2>
    </x-slot>

    <div class="card-title"> {{ __('Create Personnel Record') }}</div>

    <form class="relative" action="{{ route('payroll.personnel.store') }}" method="POST">
        @csrf
        @include('personnel.form')

        <div class="relative mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Create') }}
            </button>
        </div>
    </form>
</x-app-layout>