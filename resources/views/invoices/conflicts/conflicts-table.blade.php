@props(['conflicts', 'invoice', 'type'])

<table class="table w-full mt-4 overflow-auto">
    <h5 class="font-semibold pt-5">{{ __(ucfirst($type)) }}</h5>
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
                <td class="px-2 py-2 w-1">{{ convertToFarsi($loop->iteration) }}</td>
                <td class="px-4 py-2">{{ $conflict->invoice_type->label() }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('invoices.show', $conflict) }}" class="text-primary link link-hover">
                        {{ formatDocumentNumber($conflict->number) ?? '' }}
                    </a>
                </td>
                <td class="px-4 py-2">
                    @if ($conflict->status->isApproved())
                        <a href="{{ route('documents.show', $conflict->document) }}"
                            class="text-primary link link-hover">
                            {{ formatDocumentNumber($conflict->document->number) ?? '' }}
                        </a>
                    @else
                        -
                    @endif
                </td>
                <td class="px-4 py-2">{{ formatDate($conflict->date) }}</td>
                <td class="px-4 py-2">
                    <a href="{{ route('customers.show', $conflict->customer) }}" class="text-primary link link-hover">
                        {{ $conflict->customer->name }}
                    </a>
                </td>
                <td class="px-4 py-2">
                    {{ formatNumber($conflict->amount - $conflict->subtraction) }}</td>
                <td class="px-4 py-2">{{ $conflict->status->label() }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
