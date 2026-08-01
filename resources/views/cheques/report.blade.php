<x-app-layout :title="__('cheques report')">
    <div class="space-y-4">
        <div class="flex justify-between"><h1 class="text-2xl font-bold">{{ __('cheques report') }}</h1><a class="btn btn-ghost btn-sm" href="{{ route('cheques.index') }}">{{ __('cheques back') }}</a></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach(\App\Enums\ChequeType::statuses() as $status)
                @php($row = $byStatus->get($status->value))
                <div class="stat bg-base-100 rounded-box shadow"><div class="stat-title">{{ $status->label() }}</div><div class="stat-value text-xl">{{ formatNumber($row?->total ?? 0) }}</div><div class="stat-desc">{{ localizeNumber($row?->count ?? 0) }} {{ __('cheques items') }}</div></div>
            @endforeach
        </div>
        <div class="alert alert-warning"><span>{{ __('cheques overdue_total') }}: <strong>{{ formatNumber($overdue) }}</strong></span></div>
        <div class="card bg-base-100 shadow-xl"><div class="card-body"><h2 class="card-title">{{ __('cheques due_next_30_days') }}</h2><div class="overflow-x-auto"><table class="table"><thead><tr><th>{{ __('cheques fields serial') }}</th><th>{{ __('cheques fields party') }}</th><th>{{ __('cheques fields due_date') }}</th><th>{{ __('cheques fields amount') }}</th></tr></thead><tbody>@forelse($upcoming as $cheque)<tr><td><a class="link" href="{{ route('cheques.show', $cheque) }}">{{ localizeNumber($cheque->serial) }}</a></td><td>{{ $cheque->party?->name }}</td><td>{{ formatDate($cheque->due_date) }}</td><td>{{ formatNumber($cheque->amount) }}</td></tr>@empty<tr><td colspan="4" class="text-center">{{ __('cheques empty') }}</td></tr>@endforelse</tbody></table></div></div></div>
    </div>
</x-app-layout>
