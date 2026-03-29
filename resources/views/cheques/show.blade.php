<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Cheque Details') }}
        </h2>
    </x-slot>

    <x-show-message-bags />

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-title">{{ __('Cheque Information') }}</div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><strong>{{ __('Amount') }}:</strong> {{ $cheque->amount }}</div>
                <div><strong>{{ __('Serial') }}:</strong> {{ $cheque->serial ?? '-' }}</div>
                <div><strong>{{ __('Cheque Number') }}:</strong> {{ $cheque->cheque_number ?? '-' }}</div>
                <div><strong>{{ __('Sayad Number') }}:</strong> {{ $cheque->sayad_number ?? '-' }}</div>
                <div><strong>{{ __('Write Date') }}:</strong> {{ $cheque->wrt_date ?? '-' }}</div>
                <div><strong>{{ __('Due Date') }}:</strong> {{ $cheque->due_date ?? '-' }}</div>
                <div><strong>{{ __('Customer') }}:</strong> {{ $cheque->customer->name ?? '-' }}</div>
                <div><strong>{{ __('Cheque Book') }}:</strong> {{ $cheque->chequeBook->title ?? '-' }}</div>
                <div><strong>{{ __('Transaction') }}:</strong> {{ $cheque->transaction->id ?? '-' }}</div>
                <div><strong>{{ __('Received') }}:</strong> {{ $cheque->is_received ? __('Yes') : __('No') }}</div>
                <div class="md:col-span-2"><strong>{{ __('Description') }}:</strong> {{ $cheque->desc ?? '-' }}</div>
            </div>

            <div class="divider"></div>

            <div class="card-title text-lg">{{ __('Cheque Histories') }}</div>

            <table class="table w-full mt-4">
                <thead>
                    <tr>
                        <th>{{ __('Action Type') }}</th>
                        <th>{{ __('From Status') }}</th>
                        <th>{{ __('To Status') }}</th>
                        <th>{{ __('Action At') }}</th>
                        <th>{{ __('Created By') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cheque->histories as $history)
                        <tr>
                            <td>{{ $history->action_type }}</td>
                            <td>{{ $history->from_status ?? '-' }}</td>
                            <td>{{ $history->to_status ?? '-' }}</td>
                            <td>{{ $history->action_at ?? '-' }}</td>
                            <td>{{ $history->creator->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-gray-500">
                                {{ __('There are no histories for this cheque.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('cheques.index') }}" class="btn btn-ghost">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
