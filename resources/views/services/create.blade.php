<x-app-layout :title="__('Create Service')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add service') }}</h2>
                <x-show-message-bags />

                @include('services.form')
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>

</x-app-layout>
