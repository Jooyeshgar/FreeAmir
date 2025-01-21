<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Customer Group') }}
        </h2>
    </x-slot>

    <div class="card bg-gray-100 shadow-xl">
        <form action="{{ route('customer-groups.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Add customer group') }}</div>
                <x-show-message-bags />

                @include('customerGroups.form')
                <div class="card-actions">
                    <button type="submit" class="btn btn-success"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
