<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bank Accounts') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('bank-accounts.create') }}" class="btn btn-primary">{{ __('Create Bank Account') }}</a>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Account name') }}</th>
                        <th class="px-4 py-2">{{ __('Account number') }}</th>
                        <th class="px-4 py-2">{{ __('Account owner') }}</th>
                        <th class="px-4 py-2">{{ __('Account type') }}</th>
                        <th class="px-4 py-2">{{ __('Bank name') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($bankAccounts as $bankAccount)
                        <tr>
                            <td class="px-4 py-2">{{ $bankAccount->name }}</td>
                            <td class="px-4 py-2">{{ $bankAccount->number }}</td>
                            <td class="px-4 py-2">{{ $bankAccount->owner }}</td>
                            <td class="px-4 py-2">{{ $bankAccount->type }}</td>
                            <td class="px-4 py-2">{{ $bankAccount->bank ? $bankAccount->bank->name : '' }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('bank-accounts.edit', $bankAccount) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('bank-accounts.destroy', $bankAccount) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $bankAccounts->links() !!}
        </div>
    </div>
</x-app-layout>
