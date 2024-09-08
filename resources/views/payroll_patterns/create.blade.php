<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Payroll Pattern') }}
        </h2>
    </x-slot>
    <div class="card-title"> {{ __('Create Payroll Pattern') }}</div>

    <form class="relative" action="{{ route('payroll.payroll_patterns.store') }}" method="POST">
        @csrf
        @include('payroll_patterns.form')

        <div class="mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Create') }}
            </button>
        </div>
    </form>
</x-app-layout>