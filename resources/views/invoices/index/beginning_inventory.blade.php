<div class="card-actions">
    @can('invoices.create')
        <a href="{{ route('invoices.create', ['invoice_type' => 'beginning_inventory']) }}" class="btn btn-primary">
            {{ __('Create Beginning Inventory') }}
        </a>
    @endcan
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 my-6">
    <x-stat-card :title="__('Total Quantity')" :value="formatNumber($invoices->totalProductsQuantity)" />
    <x-stat-card :title="__('Total Value')" :value="formatNumber($invoices->totalAmount)" />
</div>

<div class="overflow-x-auto">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('Invoice Number') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Warehouse') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Quantity') }}</th>
                <th>{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ formatDocumentNumber($invoice->number) }}</td>
                    <td>{{ $invoice->title }}</td>
                    <td>{{ $invoice->warehouse?->name }}</td>
                    <td>{{ $invoice->date ? formatDate($invoice->date) : '' }}</td>
                    <td>{{ formatNumber($invoice->items->sum('quantity')) }}</td>
                    <td>
                        <div class="inline-flex gap-2">
                            @can('invoices.edit')
                                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                            @endcan
                            @can('invoices.destroy')
                                <form id="delete-beginning-inventory-{{ $invoice->id }}" action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                                    onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">{{ __('No beginning inventory records found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $invoices->withQueryString()->links() }}
