<x-app-layout :title="__('Yearly Tax Slabs')">
    <x-show-message-bags />
    @php
        $fiscalYearClosed = activeFiscalYearIsClosed();
    @endphp

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('salary.tax-slabs.create')
                    <a href="{{ route('salary.tax-slabs.create') }}" @class(['btn btn-sm btn-primary', 'pointer-events-none opacity-60' => $fiscalYearClosed]) aria-disabled="{{ $fiscalYearClosed ? 'true' : 'false' }}">
                        {{ __('Create Yearly Tax Slab') }}
                    </a>
                @endcan
            </div>

            <div class="overflow-auto">
                <table class="table w-full mt-4">
                    <thead>
                        <tr>
                            <th>{{ __('Row') }}</th>
                            <th>{{ __('Income From') }}</th>
                            <th>{{ __('Income To') }}</th>
                            <th>{{ __('Tax Rate') }} (%)</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $incomeFrom = 0;
                        @endphp
                        @forelse ($taxSlabs as $taxSlab)
                            <tr>
                                <td>{{ formatNumber($loop->index) }}</td>
                                <td>{{ formatNumber($incomeFrom) }}</td>
                                <td>{{ $taxSlab->income_to !== null ? formatNumber($taxSlab->income_to) : '∞' }}</td>
                                <td>{{ $taxSlab->tax_rate }}</td>
                                <td>
                                    <div class="inline-flex gap-2">
                                        @can('salary.tax-slabs.edit')
                                            <a href="{{ route('salary.tax-slabs.edit', $taxSlab) }}" @class(['btn btn-sm btn-info', 'pointer-events-none opacity-60' => $fiscalYearClosed]) aria-disabled="{{ $fiscalYearClosed ? 'true' : 'false' }}">
                                                {{ __('Edit') }}
                                            </a>
                                        @endcan
                                        @can('salary.tax-slabs.delete')
                                            <form action="{{ route('salary.tax-slabs.destroy', $taxSlab) }}" method="POST" class="inline-block"
                                                onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" @disabled($fiscalYearClosed) class="btn btn-sm btn-error {{ $fiscalYearClosed ? 'opacity-60' : '' }}" title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : __('Delete') }}">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @php
                                $incomeFrom = $taxSlab->income_to;
                            @endphp
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-gray-500">
                                    {{ __('No tax slabs found.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {!! $taxSlabs->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
