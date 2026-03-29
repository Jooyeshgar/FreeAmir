<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Cheque Book') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('cheque-books.update', $chequeBook) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="card-title">{{ __('Edit cheque book') }}</div>

                <x-show-message-bags />

                @include('cheque-books.form')

                <div class="card-actions justify-end">
                    <button class="btn btn-primary">{{ __('Edit') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
