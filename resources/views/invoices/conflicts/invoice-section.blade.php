<h3 class="font-semibold pt-5 text-lg">
    {{ __('Invoice') . ' ' . ($invoice->invoice_type?->label() ?? '-') . ' #' . formatDocumentNumber($invoice->number) }}
</h3>

<dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mt-2">
    <div class="bg-base-100 p-3 rounded-md border">
        <dt class="text-xs text-gray-500">{{ __('Date') }}</dt>
        <dd class="text-sm font-semibold mt-1">
            {{ isset($invoice->date) ? formatDate($invoice->date) : '-' }}</dd>
    </div>

    <div class="bg-base-100 p-3 rounded-md border">
        <dt class="text-xs text-gray-500">{{ __('Customer') }}</dt>
        <dd class="text-sm font-semibold mt-1">
            @if ($invoice->customer)
                <a href="{{ route('customers.show', $invoice->customer) }}" class="text-primary link link-hover">
                    {{ $invoice->customer->name }}
                </a>
            @else
                -
            @endif
        </dd>
    </div>

    <div class="bg-base-100 p-3 rounded-md border">
        <dt class="text-xs text-gray-500">{{ __('Price') }}</dt>
        <dd class="text-sm font-semibold mt-1">
            {{ isset($invoice->amount) ? formatNumber($invoice->amount) : formatNumber(0) }}</dd>
    </div>

    <div class="bg-base-100 p-3 rounded-md border">
        <dt class="text-xs text-gray-500">{{ __('Status') }}</dt>
        <dd class="text-sm font-semibold mt-1">
            @if ($invoice->status)
                <span class="badge badge-sm badge-outline">{{ $invoice->status?->label() }}</span>
            @else
                -
            @endif
        </dd>
    </div>
</dl>
