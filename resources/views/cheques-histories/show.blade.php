<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheque History Details') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-title">{{ __('Cheque History Information') }}</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><strong>{{ __('Cheque') }}:</strong> {{ $chequeHistory->cheque->serial ?? '-' }}</div>
                <div><strong>{{ __('Action Type') }}:</strong> {{ $chequeHistory->action_type }}</div>
                <div><strong>{{ __('From Status') }}:</strong> {{ $chequeHistory->from_status ?? '-' }}</div>
                <div><strong>{{ __('To Status') }}:</strong> {{ $chequeHistory->to_status ?? '-' }}</div>
                <div><strong>{{ __('Action At') }}:</strong> {{ $chequeHistory->action_at ?? '-' }}</div>
                <div><strong>{{ __('Amount') }}:</strong> {{ $chequeHistory->amount ?? '-' }}</div>
                <div><strong>{{ __('Created By') }}:</strong> {{ $chequeHistory->creator->name ?? '-' }}</div>
                <div class="md:col-span-2"><strong>{{ __('Description') }}:</strong> {{ $chequeHistory->desc ?? '-' }}
                </div>
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('cheque-histories.index') }}" class="btn btn-ghost">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
