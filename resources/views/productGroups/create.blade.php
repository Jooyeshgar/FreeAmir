<x-app-layout :title="__('Create Product Group')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('product-groups.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add product group') }}</h2>
                <x-show-message-bags />
                @include('productGroups.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
