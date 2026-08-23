<x-app-layout :title="__('Create warehouse')">
    <div class="card bg-base-100 shadow">
        <form method="POST" action="{{ route('warehouses.store') }}">
            @csrf
            <div class="card-body">
                <h1 class="card-title">{{ __('Create warehouse') }}</h1>
                <x-show-message-bags />
                @include('warehouses.form')
                <div class="card-actions justify-end">
                    <a href="{{ route('warehouses.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
