<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Benefit or Deduction') }}
        </h2>
    </x-slot>
    <div class="card-title">{{ __('Add Benefit or Deduction') }}</div>
    <form action="{{ route('payroll.benefits_deductions.store') }}" method="POST" class="relative">
        <div class="card bg-gray-100 shadow-xl rounded-xl ">

            @csrf
            <div class="card-body p-4">
                <x-show-message-bags />

                @include('benefits_deductions.form')

            </div>

        </div>
        <div class="mt-2 flex justify-end ">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Create') }}
            </button>
        </div>
    </form>
</x-app-layout>
