@php
    $conflictsById = $numberingConflicts->keyBy(fn ($row) => $row['invoice']->id);
    $reasonLabels = [
        \App\Services\InvoiceNumberService::REASON_DUPLICATE => __('Duplicate number'),
        \App\Services\InvoiceNumberService::REASON_OUT_OF_ORDER => __('Out of order by date'),
    ];
    $sortLink = function (string $column) {
        $isCurrent = request('sort') === $column;
        $direction = $isCurrent && request('direction') === 'asc' ? 'desc' : 'asc';

        return [route('invoices.index', array_merge(request()->except('page'), ['sort' => $column, 'direction' => $direction])), $isCurrent ? (request('direction') === 'asc' ? '▲' : '▼') : ''];
    };
    [$numberSortUrl, $numberSortMark] = $sortLink('number');
    [$dateSortUrl, $dateSortMark] = $sortLink('date');
@endphp

<form action="{{ route('invoices.index') }}" method="GET" class="flex flex-wrap items-end gap-2 mb-4">
    <select name="invoice_type" class="select select-sm w-full lg:w-48">
        <option value="">{{ __('All types') }}</option>
        @foreach (\App\Enums\InvoiceType::cases() as $type)
            <option value="{{ $type->valueName() }}" @selected(request('invoice_type') === $type->valueName())>{{ $type->label() }}</option>
        @endforeach
    </select>
    <select name="status" class="select select-sm w-full lg:w-48">
        <option value="">{{ __('All statuses') }}</option>
        @foreach (\App\Enums\InvoiceStatus::cases() as $status)
            <option value="{{ $status->valueName() }}" @selected(request('status') === $status->valueName())>{{ $status->label() }}</option>
        @endforeach
    </select>
    @foreach (['sort', 'direction'] as $keep)
        @if (request()->filled($keep))
            <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
        @endif
    @endforeach
    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Filter') }}</button>
</form>

<div class="mb-6">
    <h3 class="text-lg font-bold mb-2">{{ __('Numbering conflicts') }}: {{ localizeNumber($numberingConflicts->count()) }}</h3>
    @if ($numberingConflicts->isEmpty())
        <x-show-messages message="{{ __('No numbering conflicts found.') }}" type="info" />
    @else
        <div class="overflow-x-auto">
            <table class="table table-sm w-full">
                <thead>
                    <tr>
                        <th>{{ __('Invoice Number') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Reason') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($numberingConflicts as $row)
                        <tr>
                            <td><a href="{{ route('invoices.show', $row['invoice']) }}" class="link link-hover">{{ formatDocumentNumber($row['invoice']->number) }}</a></td>
                            <td>{{ $row['invoice']->invoice_type?->label() }}</td>
                            <td>{{ formatDate($row['invoice']->date) }}</td>
                            <td>{{ $reasonLabels[$row['reason']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="overflow-x-auto">
    <table class="table w-full">
        <thead>
            <tr>
                <th><a href="{{ $numberSortUrl }}" class="link link-hover">{{ __('Invoice Number') }} {{ $numberSortMark }}</a></th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Customer') }}</th>
                <th><a href="{{ $dateSortUrl }}" class="link link-hover">{{ __('Date') }} {{ $dateSortMark }}</a></th>
                <th>{{ __('Price') }} ({{ config('amir.currency') ?? __('Rial') }})</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Action') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoices as $invoice)
                <tr @class(['bg-warning/20' => $conflictsById->has($invoice->id)])>
                    <td><a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">{{ formatDocumentNumber($invoice->number) }}</a></td>
                    <td>{{ $invoice->invoice_type?->label() }}</td>
                    <td>{{ $invoice->customer->name ?? '' }}</td>
                    <td>{{ isset($invoice->date) ? formatDate($invoice->date) : '' }}</td>
                    <td>{{ isset($invoice->amount) ? formatNumber($invoice->amount - $invoice->subtraction) : '' }}</td>
                    <td>{{ $invoice->status?->label() ?? '' }}</td>
                    <td><a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-info">{{ __('Show') }}</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{ $invoices->withQueryString()->links() }}
