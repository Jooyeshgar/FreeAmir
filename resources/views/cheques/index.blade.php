<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheques') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            <div class="flex mt-4">
                <a href="{{ route('cheques.create', $chequeBook) }}" class="btn btn-primary">
                    {{ __('Create Cheque') }}
                </a>
            </div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Serial Number') }}</th>
                        <th>{{ __('Amount') }}</th>
                        <th>{{ __('Customer') }}</th>
                        <th>{{ __('Cheque Book') }}</th>
                        <th>{{ __('Due Date') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cheques as $cheque)
                        <tr>
                            <td>{{ convertToFarsi($cheque->serial) ?? '-' }}</td>
                            <td>{{ formatNumber($cheque->amount) }}</td>
                            <td>
                                <a
                                    href="{{ route('customers.show', $cheque->customer) }}">{{ $cheque->customer->name }}</a>
                            </td>
                            <td>
                                <a
                                    href="{{ route('cheque-books.show', $cheque->chequeBook) }}">{{ $cheque->chequeBook->title }}</a>
                            </td>
                            <td>{{ formatDate($cheque->due_date) ?? '-' }}</td>
                            <td> {{ $cheque->is_received ? __('Receivable') : __('Payable') }}</td>
                            <td class="flex gap-2">
                                <a href="{{ route('cheques.show', [$cheque->chequeBook, $cheque]) }}"
                                    class="btn btn-sm">{{ __('Show') }}</a>
                                <a href="{{ route('cheques.edit', [$cheque->chequeBook, $cheque]) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>

                                <form action="{{ route('cheques.destroy', $cheque) }}" method="POST"
                                    class="inline-block m-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error"
                                        onclick="return confirm('{{ __('Are you sure?') }}')">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-gray-500">
                                {{ __('There are no cheques.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($cheques->hasPages())
                <div class="mt-4 flex justify-center">
                    {!! $cheques->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
