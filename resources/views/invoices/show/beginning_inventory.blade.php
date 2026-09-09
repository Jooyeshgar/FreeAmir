<x-app-layout :title="__('Beginning Inventory') . ' #' . formatDocumentNumber($invoice->number)">
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body gap-6">
            <x-show-message-bags />

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">{{ $invoice->title }}</h1>
                    <p class="text-sm text-base-content/70">
                        {{ __('Beginning Inventory') }} #{{ formatDocumentNumber($invoice->number) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-lg badge-primary">{{ $invoice->status->label() }}</span>
                    @if ($invoice->warehouse)
                        <a class="badge badge-lg badge-secondary link"
                            href="{{ route('warehouses.show', $invoice->warehouse) }}">{{ $invoice->warehouse->name }}</a>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-stat-card :title="__('Date')" :value="$invoice->date ? formatDate($invoice->date) : ''" />
                <x-stat-card :title="__('Total Quantity')" :value="formatNumber($invoice->items->sum('quantity'))" />
                <x-stat-card :title="__('Total Value')" :value="formatNumber($invoice->amount)" />
            </div>

            @if ($invoice->description)
                <div class="alert bg-base-200"><span>{{ $invoice->description }}</span></div>
            @endif

            @if ($changeStatusValidation->hasErrors() || $changeStatusValidation->hasWarning())
                <x-show-messages :message="$changeStatusValidation->toDetailText()" type="alert" />
            @endif

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Product') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Quantity') }}</th>
                            <th>{{ __('Unit price') }}</th>
                            <th>{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice->items as $index => $item)
                            <tr>
                                <td>{{ localizeNumber($index + 1) }}</td>
                                <td>
                                    @if ($item->itemable)
                                        <a class="link link-primary" href="{{ route('products.show', $item->itemable) }}">
                                            {{ $item->itemable->name }}
                                        </a>
                                    @else
                                        {{ __('Removed product/service') }}
                                    @endif
                                </td>
                                <td>{{ $item->description }}</td>
                                <td>{{ formatNumber($item->quantity) }}</td>
                                <td>{{ formatNumber($item->unit_price) }}</td>
                                <td>{{ formatNumber($item->quantity * $item->unit_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-actions justify-end">
                <a class="btn" href="{{ route('invoices.index', ['invoice_type' => 'beginning_inventory']) }}">{{ __('Back') }}</a>
                @can('invoices.edit')
                    @unless ($invoice->status->isApprovedOrSettled())
                        <a class="btn btn-primary" href="{{ route('invoices.edit', $invoice) }}">{{ __('Edit invoice') }}</a>
                    @endunless
                @endcan
                @can('invoices.approve')
                    @if ($changeStatusValidation->hasErrors())
                        <a class="btn btn-accent" href="{{ route('invoices.conflicts', $invoice) }}">{{ __('Fix Conflict') }}</a>
                    @else
                        <form action="{{ route('invoices.change-status', [$invoice, $invoice->status->isApprovedOrSettled() ? 'unapproved' : 'approved']) }}{{ $changeStatusValidation->hasWarning() ? '?confirm=1' : '' }}"
                            method="POST">
                            @csrf
                            <button class="btn {{ $invoice->status->isApprovedOrSettled() ? 'btn-warning' : 'btn-success' }}" type="submit">
                                {{ $invoice->status->isApprovedOrSettled() ? __('Unapprove') : __('Approve') }}
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
