<h3 class="font-semibold pt-5 text-lg">
    {{ __('Invoice') . ' ' . ($invoice->invoice_type?->label() ?? '-') . ' #' . formatDocumentNumber($invoice->number) }}
</h3>

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 mt-2">
    <x-stat-card :title="__('Date')" :value="isset($invoice->date) ? formatDate($invoice->date) : '-'" />

    <x-stat-card :title="__('Customer')">
        <a href="{{ route('customers.show', $invoice->customer) }}" class="text-primary link link-hover">
            {{ $invoice->customer->name }}
        </a>
    </x-stat-card>

    <x-stat-card :title="__('Price')" :value="isset($invoice->amount) ? formatNumber($invoice->amount) : formatNumber(0)" />

    <x-stat-card :title="__('Status')">
        {{ $invoice->status?->label() }}
    </x-stat-card>
</div>
