<x-app-layout :title="__('Moadian Histories')">
    <div class="card bg-base-100">
        <div class="card-body">
            <h2 class="card-title">
                @if(isset($invoice))
                    {{ __('Moadian Histories') }}
                    <a href="{{ route('invoices.show', $invoice) }}" class="link-hover">
                        {{ __('Invoice') . ' ' . $invoice->invoice_type->label() . ' #' . formatDocumentNumber($invoice->number ?? $invoice->id) }}
                    </a>
                @else
                    {{ __('Moadian Histories') }}
                @endif
            </h2>

            <x-show-message-bags />
            <div>
                <form method="GET" action="{{ route('invoices.moadian-histories.index') }}" class="flex flex-wrap items-end gap-4">
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
                        <x-date-picker name="date" id="date" value="{{ request('date') }}" class="mt-1 block w-full" />
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                        <a href="{{ route('invoices.moadian-histories.index') }}" class="btn">{{ __('Clear') }}</a>
                    </div>
                </form>
            </div>

            <div>
                <table class="w-full divide-y">
                    <thead>
                        <tr>
                            @if(!isset($invoice))
                                <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Invoice Number') }}</th>
                            @endif
                            <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Status') }}</th>
                            <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Reference Number') }}</th>
                            <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('UID') }}</th>
                            <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Date') }}</th>
                            <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @forelse ($moadianHistories as $history)
                            @php
                                $status = isset($history->data['status']) ? Str::upper($history->data['status']) : 'UNKNOWN';
                                $isLatest = $latestHistoryId === null || $history->id === $latestHistoryId;
                            @endphp
                            <tr>
                                @if(!isset($invoice))
                                <td class="text-sm text-gray-500">
                                    <a class="link" href="{{ route('invoices.show', $history->invoice_id) }}">{{ formatDocumentNumber($history->invoice->number) ?? '' }}</a>
                                </td>
                                @endif
                                <td>
                                    <span class="text-xs
                                        {{ $status === 'SUCCESS' ? 'bg-green-100 text-green-500' : ($status === 'FAILED' ? 'bg-red-100 text-red-500' : 'bg-yellow-100 text-yellow-500') }}">
                                        {{ __($status) }}
                                    </span>
                                </td>
                                <td class="text-sm text-gray-500">{{ $history->data['referenceNumber'] ?? '-' }}</td>
                                <td class="text-sm text-gray-500 text-xs break-all">{{ $history->data['uid'] ?? '-' }}</td>
                                <td class="text-sm text-gray-500">{{ convertToFarsi($history->created_at->format('Y/m/d H:i')) }}</td>
                                <td class="py-2">
                                    @if($isLatest)
                                        <div class="flex items-center justify-center gap-2">
                                            @if(isset($history->data['referenceNumber']) && $status === 'UNKNOWN')
                                                @can('invoices.moadian-check-status')
                                                    <form method="POST" action="{{ route('invoices.moadian-check-status', $history->invoice_id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-xs btn-info">{{ __('Check Status') }}</button>
                                                    </form>
                                                @endcan
                                            @endif
                                            @if($status === 'FAILED')
                                                @can('invoices.send-moadian')
                                                    <a href="{{ route('invoices.moadian-form', $history->invoice_id) }}" class="btn btn-xs btn-info">{{ __('Send Again') }}</a>
                                                @endcan
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ isset($invoice) ? 5 : 6 }}" class="text-sm text-center text-gray-500 p-3">{{ __('No moadian histories found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(isset($invoice))
                <div class="card-actions justify-end">
                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-ghost gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        {{ __('Back') }}
                    </a>
                </div>
            @endif

            <div class="border-gray-200">
                {{ $moadianHistories->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
