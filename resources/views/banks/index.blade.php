<x-app-layout :title="__('Banks')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-base-content">{{ __('Banks') }}</h1>
                <x-user-guide-link source="management/finance/banks/banks.md" />
            </div>
            <div class="card-actions">
                <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('banks.create') }}" class="btn btn-primary">{{ __('Create Bank') }}</x-closed-year-action-link>

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
                                        <x-closed-year-action-link :fiscal-year-closed="$fiscalYearClosed" href="{{ route('banks.edit', $bank) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</x-closed-year-action-link>
                                        <form action="{{ route('banks.destroy', $bank) }}" method="POST">
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
            </div>
            {{ $banks->links() }}
        </div>
</x-app-layout>
