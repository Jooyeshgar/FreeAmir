<x-app-layout :title="__('Conflicts')">
    <x-show-message-bags />

    @php $fiscalYearClosed = $invoice->fiscalYear?->isClosed() ?? false; @endphp
    @if ($fiscalYearClosed)
        <div class="alert alert-warning mb-4">
            <span>{{ __('This fiscal year is already closed.') }} {{ __('Invoice and settlement operations are read-only.') }}</span>
        </div>
    @endif

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <x-show-messages type="warning" message="{{ __('conflict_notice') }}" />

            @include('invoices.conflicts.header', ['invoice' => $invoice])

            @foreach ($conflicts as $type => $conflict)
                @if ($conflict->isEmpty())
                    @continue
                @endif

                @include('invoices.conflicts.table', [
                    'conflicts' => $conflict,
                    'invoice' => $invoice,
                    'type' => $type,
                ])

                @if ($conflict->hasPages())
                    <div class="px-4 py-2 text-center">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <a href="{{ route('invoices.conflicts.more', [
                                    'invoice' => $invoice,
                                    'type' => $type,
                                ]) }}"
                                    class="btn btn-sm btn-primary">
                                    {{ __('Show All ' . ucfirst($type)) }} ({{ $conflict->total() }})
                                </a>
                            </td>
                        </tr>
                    </div>
                @endif
            @endforeach

            @if ($allowedToResolve)
                <div class="px-4 py-2 text-left">
                    <form method="POST" action="{{ route('invoices.group-action', $invoice) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary {{ $fiscalYearClosed ? 'btn-disabled cursor-not-allowed' : '' }}"
                            @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : '' }}">{{ __('Delete All Documents') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
