<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tax Slabs') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                @can('salary.tax-slabs.create')
                    <a href="{{ route('tax-slabs.create') }}" class="btn btn-primary">
                        {{ __('Create Tax Slab') }}
                    </a>
                @endcan
            </div>

            <form action="{{ route('tax-slabs.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-2/5">
                    <div class="relative">
                        <input type="number" name="year" value="{{ request('year') }}" placeholder="{{ __('Filter by year') }}"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>
                    <div class="flex items-center">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                            {{ __('Search') }}
                        </button>
                    </div>
                </div>
            </form>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th>{{ __('Year') }}</th>
                        <th>{{ __('Slab Order') }}</th>
                        <th>{{ __('Income From') }}</th>
                        <th>{{ __('Income To') }}</th>
                        <th>{{ __('Tax Rate') }} (%)</th>
                        <th>{{ __('Annual Exemption') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($taxSlabs as $taxSlab)
                        <tr>
                            <td>{{ $taxSlab->year }}</td>
                            <td>{{ $taxSlab->slab_order }}</td>
                            <td>{{ formatNumber($taxSlab->income_from) }}</td>
                            <td>{{ $taxSlab->income_to !== null ? formatNumber($taxSlab->income_to) : '∞' }}</td>
                            <td>{{ $taxSlab->tax_rate }}</td>
                            <td>{{ $taxSlab->annual_exemption !== null ? formatNumber($taxSlab->annual_exemption) : '-' }}</td>
                            <td class="flex gap-2">
                                @can('salary.tax-slabs.edit')
                                    <a href="{{ route('tax-slabs.edit', $taxSlab) }}" class="btn btn-sm btn-info">
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('salary.tax-slabs.delete')
                                    <form action="{{ route('tax-slabs.destroy', $taxSlab) }}" method="POST" class="inline-block"
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
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-gray-500">
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
