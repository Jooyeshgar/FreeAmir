<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Subject') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('subjects.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Edit subject') }}</div>
                <x-show-message-bags />

                @include('subjects.form')
                <div class="mb-6">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>