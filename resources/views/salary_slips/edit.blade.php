<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Salary Slip') }}
        </h2>
    </x-slot>
    
    <div class="card-title"> {{ __('Edit Salary Slip') }}</div>

    <form class="relative" action="{{ route('payroll.salary_slips.update', $salarySlip) }}" method="POST">
        @method('PUT')
        @csrf
        @include('salary_slips.form')

        <div class="mt-2 flex justify-end">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Update') }}
            </button>
        </div>
    </form>
</x-app-layout>
