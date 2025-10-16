<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Ancillary Cost') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('ancillary-costs.update', $ancillaryCost) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit Ancillary Cost') }}</div>
                <x-show-message-bags />

                @include('ancillaryCosts.form')
                <div class="card-actions justify-end">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>