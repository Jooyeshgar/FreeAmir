<x-app-layout :title="__('Edit bank account')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('bank-accounts.update', $bankAccount) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <div class="card-title">{{ __('Edit bank account') }}</div>
                <x-show-message-bags />

                @include('bankAccounts.form')
                <div class="card-actions">
                    <button class="btn btn-pr"> {{ __('Edit') }} </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
