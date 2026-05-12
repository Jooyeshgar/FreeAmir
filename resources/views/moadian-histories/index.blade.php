<x-app-layout :title="__('Moadian Histories')">
    <div class="card bg-base-100">
        <div class="overflow-hidden p-6">
            <form method="GET" action="{{ route('invoices.moadian-histories.index') }}" class="flex flex-wrap items-end gap-4">
                <div>
                    <label for="status" class="label text-sm">{{ __('Status') }}</label>
                    <select name="status" id="status" class="select w-full">
                        <option value="">{{ __('All') }}</option>
                        <option value="SUCCESS" {{ request('status') == 'SUCCESS' ? 'selected' : '' }}>{{ __('Success') }}</option>
                        <option value="FAILED" {{ request('status') == 'FAILED' ? 'selected' : '' }}>{{ __('Failed') }}</option>
                        <option value="PENDING" {{ request('status') == 'PENDING' ? 'selected' : '' }}>{{ __('Pending') }}</option>
                    </select>
                </div>

                <div>
                    <label for="date" class="label text-sm">{{ __('Date') }}</label>
                    <x-date-picker name="date" id="date" value="{{ request('date') }}" class="mt-1 block w-full" />
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="btn">{{ __('Filter') }}</button>
                    <a href="{{ route('invoices.moadian-histories.index') }}" class="btn">{{ __('Clear') }}</a>
                </div>
            </form>
        </div>

        <div>
            <table class="w-full divide-y">
                <thead>
                    <tr>
                        <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Invoice Number') }}</th>
                        <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Status') }}</th>
                        <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Reference Number') }}</th>
                        <th scope="col" class="px-2 py-2 text-xs text-gray-500">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($moadianHistories as $history)
                        @php
                            $data = json_decode($history->data, true);
                            $status = STR::upper($data['status']) ?? 'UNKNOWN';
                        @endphp
                        <tr>
                            <td class="text-sm text-gray-500">
                                <a class="link" href="{{ route('invoices.show', $history->invoice_id) }}">{{ formatDocumentNumber($history->invoice->number) ?? '' }}</a>
                            </td>
                            <td>
                                <span class="text-xs
                                    {{ $status === 'SUCCESS' ? 'bg-green-100 text-green-500' : ($status === 'FAILED' ? 'bg-red-100 text-red-500' : 'bg-yellow-100 text-yellow-500') }}">
                                    {{ $status }}
                                </span>
                            </td>
                            <td class="text-sm text-gray-500">{{ $data['referenceNumber'] ?? '-' }}</td>
                            <td class="text-sm text-gray-500">{{ convertToFarsi($history->created_at->format('Y/m/d H:i')) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-sm text-center text-gray-500 px-2 py-2">{{ __('No moadian histories found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-gray-200">
            {{ $moadianHistories->links() }}
        </div>
    </div>
</x-app-layout>
