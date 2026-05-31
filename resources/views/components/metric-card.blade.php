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
    $title = $title ?? (data_get($card, 'title') ?? data_get($card, 'label'));
    $rawValue = $value ?? data_get($card, 'value', 0);
    $suffix = $suffix ?? (data_get($card, 'suffix') ?? data_get($card, 'unit'));
    $detail = $detail ?? data_get($card, 'detail');
    $change = $change ?? data_get($card, 'change');
    $sparkline = $sparkline ?? data_get($card, 'sparkline');
    $series = $series ?? data_get($card, 'series');
    $tone = $tone ?? data_get($card, 'tone', 'info');
    $displayValue = is_numeric($rawValue) ? formatNumber($rawValue) : $rawValue;

    $toneClasses = [
        'info' => [
            'card' =>
                'border-sky-200/80 bg-gradient-to-br from-base-100 via-sky-50/70 to-sky-100/50 shadow-sky-900/5 dark:border-sky-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-sky-950/25 dark:shadow-black/20',
            'icon' => 'bg-sky-500/10 text-sky-600 ring-sky-500/15 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-300/10',
            'stroke' => '#38bdf8',
        ],
        'error' => [
            'card' =>
                'border-rose-200/80 bg-gradient-to-br from-base-100 via-rose-50/70 to-rose-100/40 shadow-rose-900/5 dark:border-rose-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-rose-950/25 dark:shadow-black/20',
            'icon' => 'bg-rose-500/10 text-rose-600 ring-rose-500/15 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-300/10',
            'stroke' => '#fb7185',
        ],
        'success' => [
            'card' =>
                'border-emerald-200/80 bg-gradient-to-br from-base-100 via-emerald-50/70 to-emerald-100/40 shadow-emerald-900/5 dark:border-emerald-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-emerald-950/25 dark:shadow-black/20',
            'icon' => 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/15 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-300/10',
            'stroke' => '#34d399',
        ],
        'primary' => [
            'card' =>
                'border-blue-200/80 bg-gradient-to-br from-base-100 via-blue-50/70 to-blue-100/40 shadow-blue-900/5 dark:border-blue-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-blue-950/25 dark:shadow-black/20',
            'icon' => 'bg-blue-500/10 text-blue-600 ring-blue-500/15 dark:bg-blue-400/10 dark:text-blue-300 dark:ring-blue-300/10',
            'stroke' => '#60a5fa',
        ],
        'warning' => [
            'card' =>
                'border-amber-200/80 bg-gradient-to-br from-base-100 via-amber-50/70 to-amber-100/40 shadow-amber-900/5 dark:border-amber-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-amber-950/25 dark:shadow-black/20',
            'icon' => 'bg-amber-500/10 text-amber-600 ring-amber-500/15 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-300/10',
            'stroke' => '#f59e0b',
        ],
        'secondary' => [
            'card' =>
                'border-violet-200/80 bg-gradient-to-br from-base-100 via-violet-50/70 to-violet-100/40 shadow-violet-900/5 dark:border-violet-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-violet-950/25 dark:shadow-black/20',
            'icon' => 'bg-violet-500/10 text-violet-600 ring-violet-500/15 dark:bg-violet-400/10 dark:text-violet-300 dark:ring-violet-300/10',
            'stroke' => '#a78bfa',
        ],
        'placeholder' => [
            'card' =>
                'border-base-300/80 bg-gradient-to-br from-base-100 via-base-100 to-base-200/70 shadow-base-content/5 dark:border-slate-700/80 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-slate-800/50 dark:shadow-black/20',
            'icon' => 'bg-base-300/40 text-base-content/70 ring-base-content/10 dark:bg-slate-700/50 dark:text-slate-300 dark:ring-white/5',
            'stroke' => '#94a3b8',
        ],
    ][$tone] ?? [
        'card' =>
            'border-base-300/80 bg-gradient-to-br from-base-100 via-base-100 to-base-200/70 shadow-base-content/5 dark:border-slate-700/80 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-slate-800/50 dark:shadow-black/20',
        'icon' => 'bg-base-300/40 text-base-content/70 ring-base-content/10 dark:bg-slate-700/50 dark:text-slate-300 dark:ring-white/5',
        'stroke' => '#94a3b8',
    ];

    $hasSeries = is_iterable($series) && collect($series)->isNotEmpty();
    $hasChart = filled($sparkline) || $hasSeries;

    if (blank($sparkline) && $hasSeries) {
        $values = collect($series)->values()->map(fn($value) => (float) $value)->all();

        $width = 180;
        $height = 56;
        $min = min($values);
        $max = max($values);
        $range = max($max - $min, 1);
        $step = count($values) > 1 ? $width / (count($values) - 1) : 0;
        $sparkline = collect($values)
            ->map(function (float $value, int $index) use ($height, $min, $range, $step) {
                $x = $index * $step;
                $y = $height - 4 - (($value - $min) / $range) * ($height - 8);

                return sprintf('%.2f,%.2f', $x, $y);
            })
            ->implode(' ');
    }

    // Build a smooth Catmull-Rom spline path from the "x,y x,y ..." points.
    $linePath = '';
    $areaPath = '';
    if ($hasChart) {
        $points = collect(explode(' ', trim($sparkline)))
            ->filter()
            ->map(function ($pair) {
                [$x, $y] = array_pad(explode(',', $pair), 2, 0);

                return ['x' => (float) $x, 'y' => (float) $y];
            })
            ->values()
            ->all();

        $count = count($points);
        if ($count > 0) {
            $linePath = sprintf('M %.2f,%.2f', $points[0]['x'], $points[0]['y']);
            for ($i = 0; $i < $count - 1; $i++) {
                $p0 = $points[max($i - 1, 0)];
                $p1 = $points[$i];
                $p2 = $points[$i + 1];
                $p3 = $points[min($i + 2, $count - 1)];

                $linePath .= sprintf(
                    ' C %.2f,%.2f %.2f,%.2f %.2f,%.2f',
                    $p1['x'] + ($p2['x'] - $p0['x']) / 12,
                    $p1['y'] + ($p2['y'] - $p0['y']) / 12,
                    $p2['x'] - ($p3['x'] - $p1['x']) / 12,
                    $p2['y'] - ($p3['y'] - $p1['y']) / 12,
                    $p2['x'],
                    $p2['y'],
                );
            }

            $areaPath = sprintf('%s L %.2f,60 L %.2f,60 Z', $linePath, $points[$count - 1]['x'], $points[0]['x']);
        }
    }

    $chartId = 'metric-chart-' . substr(md5((string) $title . (string) $tone . (string) $sparkline), 0, 10);
@endphp

<article
    {{ $attributes->merge(['class' => 'card relative min-h-32 overflow-hidden rounded-lg border shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md ' . $toneClasses['card']]) }}>
    @if ($hasChart)
        <svg class="pointer-events-none absolute inset-x-0 bottom-0 h-[54%] w-full" viewBox="0 0 180 60" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="{{ $chartId }}-fill" x1="0" x2="0" y1="0" y2="60" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="{{ $toneClasses['stroke'] }}" stop-opacity="0.35" />
                    <stop offset="100%" stop-color="{{ $toneClasses['stroke'] }}" stop-opacity="0" />
                </linearGradient>
            </defs>
            <path d="{{ $areaPath }}" fill="url(#{{ $chartId }}-fill)" />
            <path d="{{ $linePath }}" fill="none" stroke="{{ $toneClasses['stroke'] }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                stroke-opacity="0.6" vector-effect="non-scaling-stroke" />
        </svg>
    @endif

    <div class="card-body relative z-10 gap-2 p-4">
        <div class="flex flex-row-reverse items-start justify-between gap-2">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 {{ $toneClasses['icon'] }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-[1.15rem] w-[1.15rem]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 4-6" />
                </svg>
            </div>
            <span class="min-w-0 text-right text-xs font-medium leading-5 text-base-content/60 dark:text-slate-300/70">{{ $title }}</span>
        </div>

        <div class="min-w-0">
            <div class="truncate text-2xl font-bold leading-8 text-base-content tabular-nums dark:text-slate-50">
                {{ $displayValue }}
            </div>
            @if (filled($suffix))
                <div class="text-xs text-base-content/60 dark:text-slate-400">{{ $suffix }}</div>
            @endif
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 text-xs">
            @if ($change !== null)
                <span class="shrink-0 font-semibold {{ $change >= 0 ? 'text-success' : 'text-error' }}">
                    {{ $change >= 0 ? '↑' : '↓' }}
                    {{ formatNumber(abs($change)) }}{{ __('Percent sign') }}
                </span>
            @else
                <span></span>
            @endif

            @if (filled($detail))
                <span class="min-w-0 truncate text-base-content/50 dark:text-slate-400/80">{{ $detail }}</span>
            @elseif ($change === null)
                <span class="min-w-0 truncate text-base-content/50 dark:text-slate-400/80">{{ __('No change') }}</span>
            @endif
        </div>
    </div>
</article>
