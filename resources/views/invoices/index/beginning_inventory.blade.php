<div class="card-actions">
    <a href="{{ route('invoices.create', ['invoice_type' => 'beginning_inventory']) }}" class="btn btn-primary">
        {{ __('Create Beginning Inventory') }}
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 my-6">
    <x-stat-card :title="__('Total Quantity')" :value="formatNumber($invoices->totalProductsQuantity)" />
    <x-stat-card :title="__('Total Value')" :value="formatNumber($invoices->totalAmount)" />
</div>

<div class="overflow-x-auto">
    <table class="table w-full">
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
                    <td>
                        <a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">
                            {{ formatDocumentNumber($invoice->number) }}
                        </a>
                    </td>
                    <td>{{ $invoice->title }}</td>
                    <td>{{ $invoice->warehouse?->name }}</td>
                    <td>{{ $invoice->date ? formatDate($invoice->date) : '' }}</td>
                    <td>{{ formatNumber($invoice->items->sum('quantity')) }}</td>
                    <td class="flex gap-2">
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                        <form action="{{ route('invoices.destroy', $invoice) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                        </form>
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
