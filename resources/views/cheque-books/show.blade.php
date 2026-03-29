<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheque Book Details') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-title">{{ __('Cheque Book Information') }}</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><strong>{{ __('Title') }}:</strong> {{ $chequeBook->title }}</div>
                <div><strong>{{ __('Issued At') }}:</strong> {{ $chequeBook->issued_at ?? '-' }}</div>
                <div><strong>{{ __('Sayad') }}:</strong> {{ $chequeBook->is_sayad ? __('Yes') : __('No') }}</div>
                <div><strong>{{ __('Status') }}:</strong> {{ $chequeBook->status ?? '-' }}</div>
                <div><strong>{{ __('Company') }}:</strong> {{ $chequeBook->company->name ?? '-' }}</div>
                <div><strong>{{ __('Bank Account') }}:</strong> {{ $chequeBook->bankAccount->title ?? '-' }}</div>
                <div class="md:col-span-2"><strong>{{ __('Description') }}:</strong> {{ $chequeBook->desc ?? '-' }}
                </div>
            </div>

            <div class="divider"></div>

            <div class="card-title text-lg">{{ __('Related Cheques') }}</div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Serial') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Due Date') }}</th>
                        <th>{{ __('Customer') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($chequeBook->cheques as $cheque)
                        <tr>
                            <td>{{ $cheque->serial ?? '-' }}</td>
                            <td>{{ $cheque->amount }}</td>
                            <td>{{ $cheque->due_date ?? '-' }}</td>
                            <td>{{ $cheque->customer->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-gray-500">
                                {{ __('There are no cheques for this cheque book.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div>
                <div class="divider text-lg font-semibold">{{ __('Related Cheques') }}</div>

                @if ($chequeBook->cheques->isNotEmpty())
                    <div class="overflow-x-auto shadow-lg rounded-lg">
                        <table class="table table-zebra w-full">
                            <thead class="bg-base-300">
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('Cheque Number') }}</th>
                                    <th>{{ __('Serial') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Due Date') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($chequeBook->cheques as $index => $cheque)
                                    <tr>
                                        <td>{{ convertToFarsi($index + 1) }}</td>
                                        <td>{{ $cheque->cheque_number ?: '—' }}</td>
                                        <td>{{ $cheque->serial ?: '—' }}</td>
                                        <td>{{ formatNumber($cheque->amount ?? 0) }}</td>
                                        <td>{{ $cheque->due_date ? formatDate($cheque->due_date) : '—' }}</td>
                                        <td>{{ $cheque->status?->label() ?? '—' }}</td>
                                        <td>
                                            <a href="{{ route('cheques.show', $cheque) }}" class="btn btn-xs btn-info">
                                                {{ __('Show') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-base-300">
                                <tr>
                                    <td colspan="7" class="text-right text-sm text-gray-600">
                                        {{ __('Total cheques: :count', ['count' => convertToFarsi($chequeBook->cheques->count())]) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <span>{{ __('There are no cheques for this cheque book.') }}</span>
                    </div>
                @endif

                @can('cheques.create')
                    <div class="flex mt-4">
                        <a href="{{ route('cheques.create', ['cheque_book_id' => $chequeBook->id]) }}"
                            class="btn btn-primary">
                            {{ __('Create Cheque') }}
                        </a>
                    </div>
                @endcan
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('cheque-books.index') }}" class="btn btn-ghost">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
