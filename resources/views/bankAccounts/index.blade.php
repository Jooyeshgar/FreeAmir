<x-app-layout :title="__('Bank Accounts')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-base-content">{{ __('Bank Accounts') }}</h1>
                <x-user-guide-link source="management/finance/bank-accounts/bank-accounts.md" />
            </div>
            <div class="card-actions">
                <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('bank-accounts.create') }}" class="btn btn-primary">{{ __('Create Bank Account') }}</x-closed-year-action-link>
            </div>
            <div class="overflow-auto">
                @if ($bankAccounts->count())
                <table class="table w-full mt-4">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Account name') }}</th>
                            <th class="px-4 py-2">{{ __('Account number') }}</th>
                            <th class="px-4 py-2">{{ __('Account owner') }}</th>
                            <th class="px-4 py-2">{{ __('Account type') }}</th>
                            <th class="px-4 py-2">{{ __('IBAN') }}</th>
                            <th class="px-4 py-2">{{ __('Bank name') }}</th>
                            <th class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach ($bankAccounts as $bankAccount)
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="{{ route('bank-accounts.show', $bankAccount) }}">{{ $bankAccount->name }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $bankAccount->number }}</td>
                                <td class="px-4 py-2">{{ $bankAccount->owner }}</td>
                                <td class="px-4 py-2">{{ $bankAccount->type?->label() }}</td>
                                <td class="px-4 py-2">{{ $bankAccount->iban ?? '-' }}</td>
                                <td class="px-4 py-2">{{ $bankAccount->bank ? $bankAccount->bank->name : '' }}</td>
                                <td class="px-4 py-2">
                                    <div class="inline-flex gap-2">
                                        <a href="{{ route('bank-accounts.show', $bankAccount) }}"
                                            class="btn btn-sm btn-info">{{ __('View') }}</a>
                                        <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('bank-accounts.edit', $bankAccount) }}"
                                            class="btn btn-sm btn-info">{{ __('Edit') }}</x-closed-year-action-link>
                                        <form action="{{ route('bank-accounts.destroy', $bankAccount) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error" @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : '' }}">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="mt-4">
                        <x-empty-state
                            :title="__('No bank accounts defined yet')"
                            :description="__('Add your business bank accounts here to record bank receipts, payments, and manage chequebooks.')"
                        >
                            <x-slot:icon>
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                </svg>
                            </x-slot:icon>
                            @if (auth()->user()?->can('banks.index') || auth()->user()?->can('banks.*'))
                                <x-slot:actions>
                                    <a href="{{ route('banks.index') }}" class="btn btn-outline btn-sm">
                                        {{ __('Banks') }}
                                    </a>
                                </x-slot:actions>
                            @endif
                        </x-empty-state>
                    </div>
                @endif
            </div>
            {!! $bankAccounts->links() !!}
        </div>
    </div>
</x-app-layout>
