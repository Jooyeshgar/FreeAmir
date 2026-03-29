<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Cheque History') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('cheque-histories.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Create cheque history') }}</div>

                <x-show-message-bags />

                @include('cheque-histories.form')

                <div class="card-actions justify-end">
                    <button class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
