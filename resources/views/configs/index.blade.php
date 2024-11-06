<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Configs') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
                <form action="{{ route('configs.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="card-title">{{ __('Edit Config') }}</div>
                        <x-show-message-bags/>

                        @include('configs.form')
                        <div class="card-actions">
                            <button type="submit" class="btn btn-pr"> {{ __('Edit') }} </button>
                        </div>
                    </div>
                </form>
        </div>
    </div>
</x-app-layout>
