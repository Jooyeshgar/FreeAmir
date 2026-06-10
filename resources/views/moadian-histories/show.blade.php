<x-app-layout :title="__('Moadian Histories') . ' ' . __('Invoice #') . formatDocumentNumber($invoice->number ?? $invoice->id)">
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">
                <a href="{{ route('invoices.show', $invoice) }}" class="link-hover">
                    {{ __('Invoice') . ' ' . $invoice->invoice_type->label() . ' #' . formatDocumentNumber($invoice->number ?? $invoice->id) }}
                </a>
            </h2>

            <x-show-message-bags />
            <div class="flex flex-wrap items-end justify-between gap-4">
                <form method="GET" action="{{ route('invoices.moadian-histories.show', $invoice) }}"
                    class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="status" class="label text-sm">{{ __('Status') }}</label>
                        <select name="status" id="status" class="select w-full">
                            <option value="">{{ __('All') }}</option>
                            <option value="SUCCESS" {{ request('status') == 'SUCCESS' ? 'selected' : '' }}>{{ __('SUCCESS') }}</option>
                            <option value="FAILED" {{ request('status') == 'FAILED' ? 'selected' : '' }}>{{ __('FAILED') }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="date" class="label text-sm">{{ __('Date') }}</label>
                        <x-date-picker placeholder="{{ __('Date') }}" name="date" id="date" value="{{ request('date') }}" class="mt-1 block w-full" />
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                        <a href="{{ route('invoices.moadian-histories.index') }}" class="btn">{{ __('Clear') }}</a>
                    </div>
                </form>

                <div>
                    @if ($latestHistoryStatus === 'UNKNOWN')
                        <form method="POST" action="{{ route('invoices.moadian-check-status', $invoice) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-warning">{{ __('Check Status') }}</button>
                        </form>
                    @elseif($latestHistoryStatus === 'FAILED')
                        <form method="POST" action="{{ route('invoices.send-moadian', $invoice) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-secondary">{{ __('Send Again') }}</button>
                        </form>
                    @elseif($latestHistoryStatus === 'SUCCESS')
                        <span class="btn btn-sm btn-disabled">{{ __('Sent') }}</span>
                    @endif
                </div>
            </div>

            <div>
                <table class="w-full divide-y">
                    <thead>
                        <tr>
                            <th class="px-2 py-2 text-xs text-gray-500">{{ __('Status') }}</th>
                            <th class="px-2 py-2 text-xs text-gray-500">{{ __('Reference Number') }}</th>
                            <th class="px-2 py-2 text-xs text-gray-500">{{ __('UID') }}</th>
                            <th class="px-2 py-2 text-xs text-gray-500">{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @forelse ($moadianHistories as $history)
                            @php
                                $status = isset($history->data['status']) ? Str::upper($history->data['status']) : 'UNKNOWN';
                            @endphp
                            <tr>
                                <td class="px-2 py-1">
                                    <span class=" {{ $status === 'SUCCESS' ? 'bg-green-100 text-green-500' : ($status === 'FAILED' ? 'bg-red-100 text-red-500' : 'bg-yellow-100 text-yellow-500') }}">
                                        {{ __($status) }}
                                    </span>
                                </td>
                                <td class="px-2 py-1 text-gray-500">{{ $history->data['referenceNumber'] ?? '-' }}</td>
                                <td class="px-2 py-1 text-gray-500">{{ $history->data['uid'] ?? '-' }}</td>
                                <td class="px-2 py-1 text-gray-500">{{ localizeNumber($history->created_at->format('Y/m/d H:i')) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-gray-500 p-3">{{ __('No moadian histories found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-actions justify-end">
                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
            </div>

            <div class="border-gray-200">
                {{ $moadianHistories->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
