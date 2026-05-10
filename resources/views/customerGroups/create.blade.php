<x-app-layout :title="__('Create Customer Group')">
    <div class="card bg-base-100 shadow-xl">
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
