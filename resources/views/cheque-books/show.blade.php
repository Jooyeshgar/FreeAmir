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
                <div><strong>{{ __('Title') }}:</strong>
                    <a href="{{ route('cheques.index', $chequeBook) }}">{{ $chequeBook->title }}</a>
                </div>
                <div><strong>{{ __('Issued At') }}:</strong> {{ formatDate($chequeBook->issued_at) ?? '-' }}</div>
                <div><strong>{{ __('Sayad') }}:</strong> {{ $chequeBook->is_sayad ? __('Yes') : __('No') }}</div>
                <div><strong>{{ __('Status') }}:</strong>
                    {{ $chequeBook->is_active ? __('Active') : __('Inactive') }}
                </div>
                <div><strong>{{ __('Bank Account') }}:</strong>
                    @if ($chequeBook->bankAccount)
                        <a
                            href="{{ route('bank-accounts.show', $chequeBook->bankAccount) }}">{{ $chequeBook->bankAccount->name }}</a>
                    @else
                        -
                    @endif
                </div>
                <div class="md:col-span-2"><strong>{{ __('Description') }}:</strong> {{ $chequeBook->desc ?? '-' }}
                </div>
            </div>

            <div>
                <div class="divider text-lg font-semibold">{{ __('Cheques') }}</div>

                @if ($chequeBook->cheques->isNotEmpty())
                    <div>
                        <table class="table w-full">
                            <thead>
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
                                @foreach ($chequeBook->cheques->take(5) as $index => $cheque)
                                    <tr>
                                        <td>{{ convertToFarsi($index + 1) }}</td>
                                        <td>{{ convertToFarsi($cheque->cheque_number) ?: '—' }}</td>
                                        <td>{{ convertToFarsi($cheque->serial) ?: '—' }}</td>
                                        <td>{{ formatNumber($cheque->amount ?? 0) }}</td>
                                        <td>{{ $cheque->due_date ? formatDate($cheque->due_date) : '—' }}</td>
                                        <td>{{ $cheque->status->label() ?? '—' }}</td>
                                        <td>
                                            <a href="{{ route('cheques.show', [$chequeBook, $cheque]) }}"
                                                class="btn btn-xs">
                                                {{ __('Show') }}
                                            </a>
                                            <a href="{{ route('cheques.edit', [$chequeBook, $cheque]) }}"
                                                class="btn btn-xs btn-info">
                                                {{ __('Edit') }}
                                            </a>
                                            <form action="{{ route('cheques.destroy', $cheque) }}" method="POST"
                                                class="inline-block m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-error"
                                                    onclick="return confirm('{{ __('Are you sure?') }}')">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <span>{{ __('There are no cheques for this cheque book.') }}</span>
                    </div>
                @endif
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('cheques.index', $chequeBook) }}" class="btn btn-primary">{{ __('Cheques') }}</a>
                <a href="{{ route('cheque-books.index') }}" class="btn btn-ghost">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
