<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Product Groups') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('product-groups.update', $productGroup) }}" method="POST">
            @method('PUT')
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit product group') }}</h2>
                <x-show-message-bags/>
                @include('productGroups.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary">{{ __('Edit') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
