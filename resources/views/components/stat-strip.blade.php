@props([
    'items' => [],
    'dir' => 'rtl',
])

@php
    $icons = [
        'document' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8m-5-5 5 5m-5-5v5h5" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5" />',
        'cup' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 7h11v7a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4V7Zm11 2h2a2 2 0 1 1 0 4h-2" />',
        'briefcase' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1m-9 0h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Zm6 0v13" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 20v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1m8-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm8 13v-1a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />',
        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 3v4m8-4v4M5 7h14M5 7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2" />',
    ];

    $colorClasses = [
        'red' => 'text-rose-500 dark:text-rose-400',
        'amber' => 'text-amber-500 dark:text-amber-300',
        'cyan' => 'text-cyan-500 dark:text-cyan-300',
        'sky' => 'text-sky-500 dark:text-sky-300',
        'green' => 'text-emerald-500 dark:text-emerald-300',
        'indigo' => 'text-indigo-600 dark:text-indigo-300',
        'violet' => 'text-violet-600 dark:text-violet-300',
        'slate' => 'text-slate-500 dark:text-slate-300',
    ];
@endphp

<div
    {{ $attributes->class([
        'stats stats-horizontal w-full overflow-x-auto rounded-2xl border border-slate-200/80 bg-white shadow-md shadow-slate-200/70',
        'dark:border-slate-700/70 dark:bg-slate-900/85 dark:shadow-none dark:ring-1 dark:ring-white/5',
    ]) }}
    dir="{{ $dir }}"
>
    @foreach ($items as $item)
        @php
            $icon = $item['iconSvg'] ?? ($icons[$item['icon'] ?? ''] ?? null);
            $tone = $item['tone'] ?? $item['color'] ?? 'slate';
            $toneClass = $colorClasses[$tone] ?? $tone;
            $href = $item['href'] ?? $item['url'] ?? null;
            $cellClasses = trim(implode(' ', [
                'stat min-w-[12rem] grid-cols-[1fr_auto] gap-x-5 px-7 py-4 text-right',
                'border-s border-slate-200/80 first:border-s-0 dark:border-slate-700/70',
                $href ? 'transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80' : '',
                $item['class'] ?? '',
            ]));
        @endphp

        @if ($href)
            <a href="{{ $href }}" class="{{ $cellClasses }}">
        @else
            <div class="{{ $cellClasses }}">
        @endif
            @if ($icon)
                <div class="stat-figure justify-self-start {{ $toneClass }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 stroke-current" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        {!! $icon !!}
                    </svg>
                </div>
            @endif

            <div class="stat-title text-xs font-medium text-slate-500 dark:text-slate-400">
                {{ $item['title'] ?? '' }}
            </div>

            <div class="stat-value mt-1 text-2xl font-bold leading-none {{ $toneClass }}">
                {{ $item['value'] ?? '' }}
            </div>

            @isset($item['description'])
                <div class="stat-desc mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ $item['description'] }}
                </div>
            @endisset
        @if ($href)
            </a>
        @else
            </div>
        @endif
    @endforeach
</div>
