<div class="overflow-x-auto">
    <table class="table budget-table">
        <thead>
            <tr>
                <th>{{ __('Subject') }}</th>
                <th>{{ __('Type') }}</th>
                <th class="text-end">{{ __('Applied Forecast') }}</th>
                <th class="text-end">{{ __('Actual') }}</th>
                <th class="text-end">{{ __('Variance') }}</th>
                <th class="w-12"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-base-200">
            @forelse ($budgetLines as $line)
                <tr class="group hover:bg-base-200/40" x-show="matches(@js($line['subject']->name.' '.$line['subject']->code), @js($line['type']))">
                    <td>
                        <div @class(['min-w-56', 'ps-6' => $line['isChild']])>
                            <div class="flex items-center gap-1.5 font-medium text-base-content">
                                @if ($line['isChild'])<span class="text-base-content/35">↳</span>@endif
                                <span>{{ $line['subject']->name }}</span>
                                @if ($line['isRemainder'])
                                    <span class="badge badge-ghost badge-xs">{{ __('Remainder excluding lower-level forecasts') }}</span>
                                @elseif (! $line['includedInSummary'])
                                    <span class="badge badge-info badge-outline badge-xs">{{ __('Included in parent total') }}</span>
                                @endif
                            </div>
                            <div class="mt-0.5 font-mono text-[11px] text-base-content/45">{{ formatCode($line['subject']->code) }}</div>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $line['type'] === 'income' ? 'badge-success' : 'badge-error' }} badge-outline badge-sm">
                            {{ $line['type'] === 'income' ? __('Income') : __('Expense') }}
                        </span>
                    </td>
                    <td class="text-end tabular-nums">
                        <div class="flex flex-wrap items-center justify-end gap-1.5 font-semibold">
                            <span>{{ formatNumber($line['forecast']) }}</span>
                            <span @class(['badge badge-xs', 'badge-primary' => $line['source'] === 'manual', 'badge-ghost' => $line['source'] === 'system'])>
                                {{ $line['source'] === 'manual' ? __('Manual Forecast') : __('System forecast') }}
                            </span>
                        </div>
                        @if ($line['source'] === 'manual')
                            <div class="mt-1 text-[11px] text-base-content/50">{{ __('System forecast') }}: {{ formatNumber($line['systemForecast']) }}</div>
                        @endif
                    </td>
                    <td class="text-end tabular-nums">
                        @if (is_null($line['actual']))
                            <span class="badge badge-warning badge-outline badge-sm">{{ __('No document') }}</span>
                        @else
                            {{ formatNumber($line['actual']) }}
                        @endif
                    </td>
                    <td class="text-end">
                        @if (is_null($line['variance']))
                            <span class="text-base-content/35">—</span>
                        @else
                            <div class="font-semibold tabular-nums {{ $line['variance'] >= 0 ? 'text-success' : 'text-error' }}">{{ formatNumber($line['variance']) }}</div>
                            <div class="text-[11px] text-base-content/45">{{ is_null($line['variancePercent']) ? '—' : localizeNumber(number_format($line['variancePercent'], 1)).'%' }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($line['budget'])
                            @can('budgets.destroy')
                                <form method="POST" action="{{ route('budgets.destroy', $line['budget']) }}" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-square btn-ghost btn-sm text-base-content/60 hover:text-error" aria-label="{{ __('Delete forecast for :subject', ['subject' => $line['subject']->name]) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 14h10l1-14" />
                                        </svg>
                                    </button>
                                </form>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-base-content/50">{{ __('No temporary income or expense subjects are available.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

