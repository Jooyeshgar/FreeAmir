@props([
    'card' => [],
    'title' => null,
    'value' => null,
    'suffix' => null,
    'detail' => null,
    'change' => null,
    'sparkline' => null,
    'tone' => null,
    'bar' => null,
])

@php
    $title = $title ?? data_get($card, 'title') ?? data_get($card, 'label');
    $rawValue = $value ?? data_get($card, 'value', 0);
    $suffix = $suffix ?? data_get($card, 'suffix') ?? data_get($card, 'unit');
    $detail = $detail ?? data_get($card, 'detail');
    $change = $change ?? data_get($card, 'change');
    $sparkline = $sparkline ?? data_get($card, 'sparkline');
    $tone = $tone ?? data_get($card, 'tone', 'info');
    $bar = $bar ?? data_get($card, 'bar');
    $displayValue = is_numeric($rawValue) ? formatNumber($rawValue) : $rawValue;

    $toneClasses = [
        'info' => ['card' => 'border-sky-500/25 bg-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-500', 'stroke' => '#38bdf8', 'bar' => 'bg-sky-500'],
        'error' => ['card' => 'border-rose-500/25 bg-rose-500/10', 'icon' => 'bg-rose-500/15 text-rose-500', 'stroke' => '#fb7185', 'bar' => 'bg-rose-500'],
        'success' => ['card' => 'border-emerald-500/25 bg-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-500', 'stroke' => '#34d399', 'bar' => 'bg-emerald-500'],
        'primary' => ['card' => 'border-primary/25 bg-primary/10', 'icon' => 'bg-primary/15 text-primary', 'stroke' => '#60a5fa', 'bar' => 'bg-primary'],
        'warning' => ['card' => 'border-amber-500/25 bg-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-500', 'stroke' => '#f59e0b', 'bar' => 'bg-amber-500'],
        'secondary' => ['card' => 'border-violet-500/25 bg-violet-500/10', 'icon' => 'bg-violet-500/15 text-violet-500', 'stroke' => '#a78bfa', 'bar' => 'bg-violet-500'],
    ][$tone] ?? ['card' => 'border-base-300 bg-base-200', 'icon' => 'bg-base-300 text-base-content', 'stroke' => '#64748b', 'bar' => 'bg-base-content/50'];

    $barClass = $bar ?: $toneClasses['bar'];
@endphp

<article {{ $attributes->merge(['class' => 'card min-h-44 border shadow-sm '.$toneClasses['card']]) }}>
    <div class="card-body gap-3 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="rounded-lg p-2 {{ $toneClasses['icon'] }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 4-6" />
                </svg>
            </div>
            <span class="text-right text-xs text-base-content/60">{{ $title }}</span>
        </div>

        <div>
            <div class="text-xl font-bold leading-8 text-base-content">
                {{ $displayValue }}
            </div>
            <div class="flex items-center justify-between gap-2 text-xs">
                <span class="shrink-0 text-base-content/60">{{ $suffix }}</span>
                @if ($change === null)
                    <span class="min-w-0 truncate text-base-content/50">{{ $detail ?: __('No change') }}</span>
                @else
                    <span class="shrink-0 {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                        {{ $change >= 0 ? '↑' : '↓' }}
                        {{ formatNumber(abs($change)) }}{{ __('Percent sign') }}
                    </span>
                @endif
            </div>
        </div>

        @if (filled($sparkline))
            <svg class="h-11 w-full overflow-visible" viewBox="0 0 180 56" preserveAspectRatio="none" aria-hidden="true">
                <polyline points="{{ $sparkline }}" fill="none" stroke="{{ $toneClasses['stroke'] }}" stroke-width="3" stroke-linecap="round"
                    stroke-linejoin="round" opacity=".9" />
            </svg>
        @else
            <div class="mt-auto flex h-11 items-end gap-1 pt-2" aria-hidden="true">
                <span class="{{ $barClass }} h-4 w-1/5 rounded-t opacity-45"></span>
                <span class="{{ $barClass }} h-7 w-1/5 rounded-t opacity-80"></span>
                <span class="{{ $barClass }} h-5 w-1/5 rounded-t opacity-55"></span>
                <span class="{{ $barClass }} h-8 w-1/5 rounded-t"></span>
                <span class="{{ $barClass }} h-6 w-1/5 rounded-t opacity-70"></span>
            </div>
        @endif
    </div>
</article>
