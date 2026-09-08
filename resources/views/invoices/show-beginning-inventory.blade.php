<x-app-layout :title="__('Beginning Inventory') . ' #' . formatDocumentNumber($invoice->number)">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body gap-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="card-title text-2xl">
                        {{ __('Beginning Inventory') }} #{{ formatDocumentNumber($invoice->number) }}
                    </h1>
                    <p class="text-base-content/70">{{ $invoice->title }}</p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary">{{ __('Edit') }}</a>
                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div><dt class="text-sm text-base-content/60">{{ __('Date') }}</dt><dd>{{ formatDate($invoice->date) }}</dd></div>
                <div><dt class="text-sm text-base-content/60">{{ __('Warehouse') }}</dt><dd>{{ $invoice->warehouse?->name }}</dd></div>
                <div><dt class="text-sm text-base-content/60">{{ __('description') }}</dt><dd>{{ $invoice->description }}</dd></div>
            </dl>

            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Unit Price') }}</th></tr></thead>
                    <tbody>
                        @foreach ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->itemable?->name }}</td>
                                <td>{{ formatNumber($item->quantity) }}</td>
                                <td>{{ formatNumber($item->unit_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <a href="{{ route('invoices.index', ['invoice_type' => 'beginning_inventory']) }}" class="btn btn-ghost self-start">{{ __('Back') }}</a>
        </div>
    </div>
</x-app-layout>
