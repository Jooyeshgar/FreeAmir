<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Yearly Tax Slabs') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('salary.tax-slabs.create')
                    <a href="{{ route('salary.tax-slabs.create') }}" class="btn btn-primary">
                        {{ __('Create Yearly Tax Slab') }}
                    </a>
                @endcan
            </div>

            <table class="table w-full mt-4 overflow-auto">
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
                            <td class="flex gap-2">
                                @can('salary.tax-slabs.edit')
                                    <a href="{{ route('salary.tax-slabs.edit', $taxSlab) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.tax-slabs.delete')
                                    <form action="{{ route('salary.tax-slabs.destroy', $taxSlab) }}" method="POST" class="inline-block"
                                        onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                        @php
                            $incomeFrom = $taxSlab->income_to;
                        @endphp
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4 text-gray-500">
                                {{ __('No tax slabs found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {!! $taxSlabs->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
