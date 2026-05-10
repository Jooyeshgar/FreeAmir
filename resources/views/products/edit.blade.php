<x-app-layout :title="__('Edit Products')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('products.update', $product) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit product') }}</div>
                <x-show-message-bags />

                @include('products.form')
                <div class="card-actions justify-end">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
