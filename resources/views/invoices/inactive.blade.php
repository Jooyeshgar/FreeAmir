<x-app-layout :title="__('Approve Inactive')">
    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="GET" action="{{ route('invoices.inactive.approve') }}">
                <table class="table w-full mt-2 overflow-auto">
                    <thead>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Invoice Number') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>{{ __('Invoice') }} {{ $invoice->invoice_type->label() }}</td>
                                <td>
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-primary link link-hover">
                                        {{ formatDocumentNumber($invoice->number) }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('customers.show', $invoice->customer) }}" class="text-primary link link-hover">
                                        {{ $invoice->customer->name }}
                                    </a>
                                </td>
                                <td>{{ $invoice->date ? formatDate($invoice->date) : '' }}</td>
                                <td>{{ formatNumber($invoice->amount - $invoice->subtraction) }}</td>
                            </tr>
                            @foreach ($invoice->ancillaryCosts as $ancillaryCost)
                                <tr class="bg-gray-50">
                                    <td class="{{ isset($invoice->allowedAncillaryCostsToResolve) && !$invoice->allowedAncillaryCostsToResolve ? 'text-red-600' : '' }}"
                                        title="{{ !empty($invoice->allowedAncillaryCostsToResolveReason) ? $invoice->allowedAncillaryCostsToResolveReason : '' }}">
                                        {{ $ancillaryCost->type->label() }}
                                    </td>
                                    <td>
                                        <a href="{{ route('invoices.show', $ancillaryCost->invoice) }}" class="text-primary link link-hover">
                                            {{ formatDocumentNumber($ancillaryCost->invoice->number) }}</a>
                                    </td>
                                    <td>
                                        <a href="{{ route('customers.show', $ancillaryCost->customer) }}" class="text-primary link link-hover">
                                            {{ $ancillaryCost->customer->name ?? '' }}
                                        </a>
                                    </td>
                                    <td>{{ $ancillaryCost->date ? formatDate($ancillaryCost->date) : '' }}</td>
                                    <td>{{ formatNumber($ancillaryCost->amount - $ancillaryCost->subtraction) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>

                {{-- TODO: No need to show the Invoice of ancillary costs those their status are not approved inactive --}}

                <div class="flex justify-end mt-4 gap-3">
                    <a href="{{ route('invoices.inactive.approve') }}" class="btn btn-primary btn-sm gap-2">
                        <span id="toggle-text">{{ __('Approve All') }}</span>
                    </a>

                </div>

                @if ($invoices->hasPages())
                    <div class="mt-4">
                        {{ $invoices->withQueryString()->links() }}
                    </div>
                @endif
        </div>
    </div>

</x-app-layout>
