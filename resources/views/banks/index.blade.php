<x-app-layout :title="__('Banks')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-base-content">{{ __('Banks') }}</h1>
                <x-user-guide-link source="management/finance/banks/banks.md" />
            </div>
            <div class="card-actions">
                <a href="{{ route('banks.create') }}" @class(['btn btn-primary', 'pointer-events-none opacity-60' => activeFiscalYearIsClosed()]) aria-disabled="{{ activeFiscalYearIsClosed() ? 'true' : 'false' }}" title="{{ activeFiscalYearIsClosed() ? __('This fiscal year is already closed.') : '' }}">{{ __('Create Bank') }}</a>

                <table class="table w-full mt-4 overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Name') }}</th>
                            <th class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach ($banks as $bank)
                            <tr>
                                <td class="px-4 py-2">{{ $bank->name }}</td>
                                <td class="px-4 py-2">
                                    <div class="inline-flex gap-2">
                                        <a href="{{ route('banks.edit', $bank) }}" @class(['btn btn-sm btn-info', 'pointer-events-none opacity-60' => activeFiscalYearIsClosed()]) aria-disabled="{{ activeFiscalYearIsClosed() ? 'true' : 'false' }}" title="{{ activeFiscalYearIsClosed() ? __('This fiscal year is already closed.') : __('Edit') }}">{{ __('Edit') }}</a>
                                        <form action="{{ route('banks.destroy', $bank) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" @disabled(activeFiscalYearIsClosed()) class="btn btn-sm btn-error {{ activeFiscalYearIsClosed() ? 'opacity-60' : '' }}" title="{{ activeFiscalYearIsClosed() ? __('This fiscal year is already closed.') : __('Delete') }}">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $banks->links() }}
        </div>
</x-app-layout>
