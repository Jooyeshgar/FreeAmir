<article class="card min-w-0 overflow-hidden border border-base-300 bg-base-100/90 shadow-sm
    print:shadow-none print:break-inside-avoid">
    <div class="flex items-center justify-between gap-2 px-5 py-4 border-b border-b-base-300
        [&_h3]:text-sm [&_h3]:font-bold [&_p]:mt-0.5 [&_p]:text-base-content/60 [&_p]:text-xs">
        <div><h3 class="{{ $type === 'income' ? 'text-success!' : 'text-error!' }}">{{ $title }}</h3><p>{{ __('Total') }}: {{ is_null($type === 'income' ? $actualIncome : $actualExpense) ? '—' : formatNumber(abs($type === 'income' ? $actualIncome : $actualExpense)).' '.$currency }}</p></div>
        <span class="text-lg {{ $type === 'income' ? 'text-success!' : 'text-error!' }}" aria-hidden="true">{{ $type === 'income' ? '↑' : '↓' }}</span>
    </div>
    <div class="px-5 pt-2 pb-3">
        @forelse ($categoryDrivers[$type] as $driver)
            @php
                $changeIsKnown = ! is_null($driver['percent']);
                $changeIsUp = $changeIsKnown && $driver['percent'] > 0;
                $changeIsDown = $changeIsKnown && $driver['percent'] < 0;
                $changeIsFavorable = $changeIsKnown && ($type === 'income' ? $driver['percent'] > 0 : $driver['percent'] < 0);
                $changeDirection = $changeIsFavorable ? 'up' : (($changeIsKnown && $driver['percent'] != 0) ? 'down' : 'neutral');
            @endphp
            <div class="py-3 [&+&]:border-t [&+&]:border-t-base-300">
                <div class="flex items-center gap-2">
                    <div class="min-w-0 [&_a]:block [&_a]:overflow-hidden [&_a]:text-base-content [&_a]:text-ellipsis [&_a]:whitespace-nowrap
                        [&_a]:text-xs [&_a]:font-semibold [&_a:hover]:text-primary [&_a:hover]:underline
                        [&_small]:font-mono [&_small]:block [&_small]:text-base-content/60
                        [&_small]:[direction:ltr] [&_small]:text-right [&_small]:text-xs">
                        <a href="{{ route('transactions.index', ['subject_id' => $driver['subject']->id]) }}">{{ $driver['subject']->name }}</a>
                    </div>
                    <div class="ms-auto min-w-0 [direction:ltr] text-left text-xs font-bold tabular-nums wrap-anywhere">{{ formatNumber($driver['amount']) }}</div>
                    <div class="w-20 [direction:ltr] text-left text-base-content/60 text-xs whitespace-nowrap
                        [&[data-direction='up']]:text-success [&[data-direction='down']]:text-error" data-direction="{{ $changeDirection }}">
                        @if ($changeIsKnown)
                            <span aria-hidden="true">{{ $changeIsUp ? '↑' : ($changeIsDown ? '↓' : '→') }}</span> {{ localizeNumber(number_format(abs($driver['percent']), 1)) }}%
                        @elseif (! is_null($driver['previous']) && $driver['previous'] <= 0)
                            {{ __('New') }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <progress @class(['progress mt-2 h-1 w-full', 'progress-primary' => $type === 'income']) value="{{ max(0, min(100, $driver['share'] ?? 0)) }}" max="100" aria-label="{{ __('Share') }}: {{ is_null($driver['share']) ? '—' : localizeNumber(number_format($driver['share'], 1)).'%' }}"></progress>
            </div>
        @empty
            <div class="px-4 py-8 text-base-content/60 text-center text-xs">{{ __('No actual second-level account activity is available.') }}</div>
        @endforelse
    </div>
</article>
