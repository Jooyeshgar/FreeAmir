<x-app-layout :title="__('Commercial Ledger Preview')">
    <div class="flex flex-wrap items-center justify-between gap-3 py-6">
        <div>
            <h1 class="text-xl font-bold text-base-content">{{ __('Commercial Ledger Preview') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">
                {{ formatDate($commercialLedger->from_date) }} {{ __('to') }} {{ formatDate($commercialLedger->to_date) }}
                · {{ $commercialLedger->ledger_type->label() }}
            </p>
            <span @class([
                'badge badge-sm mt-2',
                'badge-warning' => $warningRowsCount > 0,
                'badge-ghost' => $warningRowsCount === 0,
            ]) title="{{ __('Rows with zero debit and credit') }}">
                {{ trans_choice(':count warning row|:count warning rows', $warningRowsCount, ['count' => localizeNumber($warningRowsCount)]) }}
            </span>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('commercial-ledgers.index') }}" class="btn btn-sm lg:btn-md">{{ __('Back') }}</a>
            <a href="{{ route('commercial-ledgers.download', $commercialLedger) }}" class="btn btn-sm lg:btn-md btn-primary">{{ __('Download') }}</a>
        </div>
    </div>

    <x-card class_body="p-0">
        <div class="overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        @foreach(\App\Services\CommercialLedgerService::HEADERS as $header)
                            <th>{{ __($header) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr @class(['bg-warning/20' => $row['debit'] === null && $row['credit'] === null])>
                            <td>{{ localizeNumber($row['row_number']) }}</td>
                            <td>{{ localizeNumber($row['date']) }}</td>
                            <td>{{ $row['general_code'] }}</td>
                            <td>{{ $row['general_title'] }}</td>
                            <td>{{ $row['subsidiary_code'] }}</td>
                            <td>{{ $row['subsidiary_title'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td>{{ $row['debit'] === null ? null : formatNumber($row['debit']) }}</td>
                            <td>{{ $row['credit'] === null ? null : formatNumber($row['credit']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-10 text-center opacity-60">{{ __('No ledger entries were found for this date range.') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    @if($rows->hasPages())
                        <tr>
                            <td colspan="9" class="border-t p-4">{{ $rows->links() }}</td>
                        </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </x-card>
</x-app-layout>
