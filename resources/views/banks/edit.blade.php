<x-app-layout :title="__('Edit Bank')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('banks.update', $bank) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit bank') }}</div>
                <x-show-message-bags />

                @include('banks.form')
                <div class="card-actions">
                    <button class="btn btn-primary"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
