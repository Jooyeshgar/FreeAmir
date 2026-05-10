<x-app-layout :title="__('Edit Services')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('services.update', $service) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit service') }}</div>
                <x-show-message-bags />

                @include('services.form')
                <div class="card-actions justify-end">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
