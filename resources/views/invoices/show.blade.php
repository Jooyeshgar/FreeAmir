
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Invoice') }} #{{ $invoice->code }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="text-gray-500">{{ __('Code') }}</div>
                    <div class="font-semibold">{{ $invoice->code }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Date') }}</div>
                    <div class="font-semibold">{{ isset($invoice->date) ? formatDate($invoice->date) : '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Customer') }}</div>
                    <div class="font-semibold">{{ $invoice->customer->name ?? '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Document') }}</div>
                    <div class="font-semibold">
                        @if($invoice->document)
                            <a class="link" href="{{ route('documents.show', $invoice->document_id) }}">{{ formatDocumentNumber($invoice->document->number) }}</a>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Amount') }}</div>
                    <div class="font-semibold">{{ isset($invoice->amount) ? formatNumber($invoice->amount) : '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('VAT') }}</div>
                    <div class="font-semibold">{{ isset($invoice->vat) ? formatNumber($invoice->vat) : '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Addition/Subtraction/Tax') }}</div>
                    <div class="font-semibold">
                        {{ __('Addition') }}: {{ formatNumber($invoice->addition ?? 0) }} -
                        {{ __('Subtraction') }}: {{ formatNumber($invoice->subtraction ?? 0) }} -
                        {{ __('Tax') }}: {{ formatNumber($invoice->tax ?? 0) }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Ship info') }}</div>
                    <div class="font-semibold">
                        {{ __('Ship date') }}: {{ $invoice->ship_date ? formatDate($invoice->ship_date) : '-' }}
                        - {{ __('Ship via') }}: {{ $invoice->ship_via ?? '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Status') }}</div>
                    <div class="font-semibold">
                        {{ ($invoice->permanent ?? false) ? __('Permanent') : __('Draft') }} ·
                        {{ ($invoice->cash_payment ?? false) ? __('Cash') : __('Credit') }} ·
                        {{ ($invoice->is_sell ?? false) ? __('Sell') : __('Buy') }}
                    </div>
                </div>
                <div class="md:col-span-2">
                    <div class="text-gray-500">{{ __('Description') }}</div>
                    <div class="font-semibold">{{ $invoice->description ?? '-' }}</div>
                </div>
            </div>

            <div class="card-actions justify-end mt-6">
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-info">{{ __('Edit') }}</a>
                <a href="{{ route('invoices.index') }}" class="btn">{{ __('Back') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>
