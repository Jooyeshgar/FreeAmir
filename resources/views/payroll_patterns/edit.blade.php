<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Payroll Pattern') }}
        </h2>
    </x-slot>
    <div class="card-title"> {{ __('Edit Payroll Pattern') }}</div>

    <form class="relative" action="{{ route('payroll.payroll_patterns.update', $payrollPattern) }}" method="POST">
        @method('PUT')
        @csrf
        @include('payroll_patterns.form')

        <div class="mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Update') }}
            </button>
        </div>
    </form>
</x-app-layout>