<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Ancillary Cost') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('ancillary-costs.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Ancillary Cost') }}</h2>
                <x-show-message-bags />

                @include('ancillaryCosts.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
