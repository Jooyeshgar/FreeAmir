<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheques') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            <form action="{{ route('cheques.index', $chequeBook) }}" method="GET">
                <div class="mt-4 mb-4 grid grid-cols-6 gap-4">
                    <div class="col-span-2 md:col-span-1">
                        <x-date-picker name="due_date" placeholder="{{ __('Due Date') }}"
                            value="{{ request('due_date') }}" class="datePicker" />
                    </div>

                    <div class="col-span-4 md:col-span-2">
                        <input type="text" name="customer_name" value="{{ request('customer_name') }}"
                            placeholder="{{ __('Customer Name') }}"
                            class="w-full pl-8 pr-2 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <input type="text" name="serial_number" value="{{ request('serial_number') }}"
                            placeholder="{{ __('Serial Number') }}"
                            class="w-full pl-8 pr-2 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        @php
                            $status = request('status') ?? 'all';
                        @endphp
                        <select name="status" id="status"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">

                            <option value="all" @selected($status === 'all')>{{ __('All Cheques') }}</option>
                            @foreach (App\Enums\ChequeStatus::cases() as $chequeStatus)
                                <option value="{{ $chequeStatus->value }} @selected($chequeStatus === $status)">
                                    {{ $chequeStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-span-2 md:col-span-1">
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i>
                            {{ __('Search') }}
                        </button>
                    </div>
                </div>
            </form>

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
                                <a href="{{ route('cheques.edit', $cheque) }}"
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

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('cheque-books.index') }}" class="btn btn-primary">
                    {{ __('Back') }}
                </a>
            </div>

            @if ($cheques->hasPages())
                <div class="mt-4 flex justify-center">
                    {!! $cheques->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
