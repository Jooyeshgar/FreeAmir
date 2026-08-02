<x-app-layout :title="__('Cheque Report')">
    <div class="space-y-4">
        <div class="flex justify-between">
            <h1 class="text-2xl font-bold">{{ __('Cheque Report') }}</h1>
            <a class="btn btn-ghost btn-sm" href="{{ route('cheques.index') }}">{{ __('Back') }}</a>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            @foreach (\App\Enums\ChequeType::statuses() as $status)
                @php($row = $byStatus->get($status->value))
                <div class="stat bg-base-100">
                    <div class="stat-title">{{ $status->label() }}</div>
                    <div class="stat-value text-xl">{{ formatNumber($row?->total ?? 0) }}</div>
                    <div class="stat-desc">{{ trans_choice(':count cheque|:count cheques', $row?->count ?? 0, ['count' => localizeNumber($row?->count ?? 0)]) }}</div>
                </div>
            @endforeach
        </div>
        <div class="alert alert-warning">
            <span>{{ __('Total amount of overdue outstanding cheques') }}:
                <strong>{{ formatNumber($overdue) }}</strong>
            </span>
        </div>
        <div class="card bg-base-100">
            <div class="card-body">
                <h2 class="card-title">{{ __('Due in the next 30 days') }}</h2>
                <div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Cheque number') }}</th>
                                <th>{{ __('Cheque serial') }}</th>
                                <th>{{ __('Account side') }}</th>
                                <th>{{ __('Due date') }}</th>
                                <th>{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($upcoming as $cheque)
                                <tr>
                                    <td>
                                        <a class="link" href="{{ route('cheques.show', $cheque) }}">{{ localizeNumber($cheque->cheque_number) ?: '—' }}</a>
                                    </td>
                                    <td>{{ localizeNumber($cheque->serial) ?: '—' }}</td>
                                    <td>{{ $cheque->customer?->name }}</td>
                                    <td>{{ formatDate($cheque->due_date) }}</td>
                                    <td>{{ formatNumber($cheque->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">{{ __('No cheques are due in the next 30 days.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
