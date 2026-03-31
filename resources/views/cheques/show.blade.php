<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheque Details') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100">
        <div class="card-body">
            <div class="card-title">{{ __('Cheque Information') }}</div>

            <div class="card bg-base-100">
                <table class="table w-full mt-4">
                    <thead>
                        <tr>
                            <th>{{ __('Serial Number') }}</th>
                            <th>{{ __('Cheque Number') }}</th>
                            <th>{{ __('Sayad Number') }}</th>
                            <th>{{ __('Amount') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Cheque Book') }}</th>
                            <th>{{ __('Write Date') }}</th>
                            <th>{{ __('Due Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ convertToFarsi($cheque->serial) ?? '-' }}</td>
                            <td>{{ convertToFarsi($cheque->cheque_number) ?? '-' }}</td>
                            <td>{{ $cheque->sayad_number ?? '-' }}</td>
                            <td>{{ formatNumber($cheque->amount) }}</td>
                            <td>
                                <a
                                    href="{{ route('customers.show', $cheque->customer) }}">{{ $cheque->customer->name }}</a>
                            </td>
                            <td>
                                {{ $cheque->is_received ? __('Receivable') : __('Payable') }}
                            </td>
                            <td>
                                <a
                                    href="{{ route('cheque-books.show', $cheque->chequeBook) }}">{{ $cheque->chequeBook->title }}</a>
                            </td>
                            <td>{{ formatDate($cheque->written_at) ?? '-' }}</td>
                            <td>{{ formatDate($cheque->due_date) ?? '-' }}</td>
                            <td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>
                                @if ($cheque->transaction)
                                    <a href="{{ route('transactions.show', $cheque->transaction) }}">
                                        <strong>{{ __('Transaction') }}:</strong>
                                        {{ $cheque->transaction->desc ?? '-' }}
                                    </a>
                                @else
                                    <strong>{{ __('Transaction') }}:</strong>
                                    {{ $cheque->transaction->desc ?? '-' }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <strong>{{ __('Description') }}:</strong>
                                {{ $cheque->desc ?? '-' }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-actions justify-between mt-4">
                <a href="{{ route('cheques.index', $cheque->chequeBook) }}" class="btn btn-primary">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
