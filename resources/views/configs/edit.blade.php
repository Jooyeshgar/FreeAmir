<x-app-layout :title="__('Edit Config')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('configs.update', $config->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="card-title">{{ __('Edit Config') }}</div>
                <x-show-message-bags />

                @include('configs.form')

                <div class="mb-6">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
