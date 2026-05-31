@props([
    'card' => [],
    'title' => null,
    'value' => null,
    'suffix' => null,
    'detail' => null,
    'change' => null,
    'sparkline' => null,
    'series' => null,
    'tone' => null,
])

@php
    $title = $title ?? data_get($card, 'title') ?? data_get($card, 'label');
    $rawValue = $value ?? data_get($card, 'value', 0);
    $suffix = $suffix ?? data_get($card, 'suffix') ?? data_get($card, 'unit');
    $detail = $detail ?? data_get($card, 'detail');
    $change = $change ?? data_get($card, 'change');
    $sparkline = $sparkline ?? data_get($card, 'sparkline');
    $series = $series ?? data_get($card, 'series');
    $tone = $tone ?? data_get($card, 'tone', 'info');
    $displayValue = is_numeric($rawValue) ? formatNumber($rawValue) : $rawValue;

    $toneClasses = [
        'info' => ['card' => 'border-sky-500/25 bg-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-500', 'stroke' => '#38bdf8'],
        'error' => ['card' => 'border-rose-500/25 bg-rose-500/10', 'icon' => 'bg-rose-500/15 text-rose-500', 'stroke' => '#fb7185'],
        'success' => ['card' => 'border-emerald-500/25 bg-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-500', 'stroke' => '#34d399'],
        'primary' => ['card' => 'border-primary/25 bg-primary/10', 'icon' => 'bg-primary/15 text-primary', 'stroke' => '#60a5fa'],
        'warning' => ['card' => 'border-amber-500/25 bg-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-500', 'stroke' => '#f59e0b'],
        'secondary' => ['card' => 'border-violet-500/25 bg-violet-500/10', 'icon' => 'bg-violet-500/15 text-violet-500', 'stroke' => '#a78bfa'],
    ][$tone] ?? ['card' => 'border-base-300 bg-base-200', 'icon' => 'bg-base-300 text-base-content', 'stroke' => '#64748b'];

    $hasSeries = is_iterable($series) && collect($series)->isNotEmpty();
    $hasChart = filled($sparkline) || $hasSeries;

    if (blank($sparkline) && $hasSeries) {
        $values = collect($series)
            ->values()
            ->map(fn ($value) => (float) $value)
            ->all();

        $width = 180;
        $height = 56;
        $min = min($values);
        $max = max($values);
        $range = max($max - $min, 1);
        $step = count($values) > 1 ? $width / (count($values) - 1) : 0;
        $sparkline = collect($values)
            ->map(function (float $value, int $index) use ($height, $min, $range, $step) {
                $x = $index * $step;
                $y = $height - 4 - (($value - $min) / $range * ($height - 8));

                return sprintf('%.2f,%.2f', $x, $y);
            })
            ->implode(' ');
    }
@endphp

<article {{ $attributes->merge(['class' => 'relative overflow-hidden card border shadow-sm '.$toneClasses['card']]) }}>
    @if ($hasChart)
        <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 w-full opacity-50" viewBox="0 0 180 56" preserveAspectRatio="none" aria-hidden="true">
            <polyline points="{{ $sparkline }}" fill="none" stroke="{{ $toneClasses['stroke'] }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    @endif

    <div class="card-body relative gap-2 p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="rounded-lg p-2 {{ $toneClasses['icon'] }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 4-6" />
                </svg>
            </div>
            <span class="text-right text-xs text-base-content/60">{{ $title }}</span>
        </div>

        <div>
            <div class="text-xl font-bold leading-7 text-base-content">
                {{ $displayValue }}
            </div>
            @if (filled($suffix))
                <div class="text-xs text-base-content/60">{{ $suffix }}</div>
            @endif
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 text-xs">
            @if ($change !== null)
                <span class="shrink-0 {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                    {{ $change >= 0 ? '↑' : '↓' }}
                    {{ formatNumber(abs($change)) }}{{ __('Percent sign') }}
                </span>
            @else
                <span></span>
            @endif

            @if (filled($detail))
                <span class="min-w-0 truncate text-base-content/50">{{ $detail }}</span>
            @elseif ($change === null)
                <span class="min-w-0 truncate text-base-content/50">{{ __('No change') }}</span>
            @endif
        </div>
    </div>
</article>
