<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Product') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add product') }}</h2>
                <x-show-message-bags />

                @include('products.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>

</x-app-layout>
