<x-app-layout :title="__('Create Bank')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('banks.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Add Bank') }}</div>
                <x-show-message-bags />

                @include('banks.form')
                <div class="card-actions">
                    <button type="submit" class="btn btn-primary"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
