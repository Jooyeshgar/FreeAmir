<x-app-layout :title="__('Create Bank Account')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('bank-accounts.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <div class="card-title">{{ __('Add bank account') }}</div>
                <x-show-message-bags />

                @include('bankAccounts.form')
                <div class="card-actions">
                    <button type="submit" class="btn btn-pr"> {{ __('Create') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
