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
                <th>{{ __('Document') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Warehouse') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Quantity') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ formatDocumentNumber($invoice->number) }}</td>
                <td>
                    @if ($invoice->document_id)
                        @can('documents.show')
                            <a href="{{ route('documents.show', $invoice->document_id) }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ formatDocumentNumber($invoice->document->number) ?? '' }}
                            </a>
                        @else
                            <span class="text-gray-500">{{ formatDocumentNumber($invoice->document->number) ?? '' }}</span>
                        @endcan
                    @endif
                </td>
                    <td>{{ $invoice->title }}</td>
                    <td>{{ $invoice->warehouse?->name }}</td>
                    <td>{{ $invoice->date ? formatDate($invoice->date) : '' }}</td>
                    <td>{{ formatNumber($invoice->items->sum('quantity')) }}</td>
                    <td>{{ $invoice->status->label() }}</td>
                    <td>
                        <div class="inline-flex gap-2">
                            <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-primary">{{ __('Show') }}</a>
                            @can('invoices.edit')
                                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                            @endcan
                            @can('invoices.destroy')
                                @if(!$invoice->status->isApprovedOrSettled())
                                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @else
                                    <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Delete') }}</button>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">{{ __('No beginning inventory records found.') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $invoices->withQueryString()->links() }}
