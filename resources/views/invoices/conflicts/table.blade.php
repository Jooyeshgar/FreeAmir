@props(['conflicts', 'invoice', 'type'])

@if ($type === 'products')
    <table class="table w-full mt-4 overflow-auto">
        <caption class="font-semibold pt-5 text-right">{{ __('Products') }}</caption>
        <thead>
            <tr>
                <th class="px-2 py-2 w-1">{{ __('Index') }}</th>
                <th class="px-4 py-2">{{ __('Name') }}</th>
                <th class="px-4 py-2">{{ __('Price') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($conflicts as $conflict)
                <tr>
                    <td class="px-2 py-2">{{ convertToFarsi($loop->iteration) }}</td>
                    <td class="px-4 py-2">{{ $conflict->name }}</td>
                    <td class="px-4 py-2">{{ formatNumber($conflict->average_cost ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <table class="table w-full mt-4 overflow-auto">
        <caption class="font-semibold pt-5 text-right">{{ __(ucfirst($type)) }}</caption>
        <thead>
            <tr>
                <th class="px-2 py-2 w-1">{{ __('Index') }}</th>
                <th class="px-4 py-2">{{ __('Type') }}</th>
                <th class="px-4 py-2">{{ __('Invoice Number') }}</th>
                <th class="px-4 py-2">{{ __('Deletable Documents') }}</th>
                <th class="px-4 py-2">{{ __('Date') }}</th>
                <th class="px-4 py-2">{{ __('Customer') }}</th>
                <th class="px-4 py-2">{{ __('Price') }}</th>
                <th class="px-4 py-2">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($conflicts as $conflict)
                <tr>
                    <td class="px-2 py-2">{{ convertToFarsi($loop->iteration) }}</td>
                    <td class="px-4 py-2">{{ $conflict->invoice_type?->label() ?? $conflict->type->label() }}</td>
                    <td class="px-4 py-2">
                        @if ($conflict->invoice)
                            <a href="{{ route('invoices.show', $conflict->invoice) }}"
                                class="text-primary link link-hover">
                                {{ formatDocumentNumber($conflict->invoice->number) }}
                            </a>
                        @else
                            <a href="{{ route('invoices.show', $conflict) }}" class="text-primary link link-hover">
                                {{ formatDocumentNumber($conflict->number) }}
                            </a>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @if ($conflict->status->isApproved() && $conflict->document)
                            <a href="{{ route('documents.show', $conflict->document) }}"
                                class="text-primary link link-hover">
                                {{ formatDocumentNumber($conflict->document->number) }}
                            </a>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ formatDate($conflict->date) }}</td>
                    <td class="px-4 py-2">
                        @if ($conflict->customer)
                            <a href="{{ route('customers.show', $conflict->customer) }}"
                                class="text-primary link link-hover">
                                {{ $conflict->customer->name }}
                            </a>
                        @else
                            {{ $conflict->customer->name }}
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ formatNumber($conflict->amount - $conflict->subtraction) }}</td>
                    <td class="px-4 py-2">{{ $conflict->status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
