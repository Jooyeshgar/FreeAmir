@props(['title', 'value' => null, 'description' => '', 'type' => 'info', 'icon' => null])

@php
    $colors = [
        'base' => [
            'text' => 'text-slate-600 dark:text-slate-300',
            'iconBg' => 'bg-slate-100/80 dark:bg-slate-700/70',
            'border' => 'border-slate-200/80 dark:border-slate-700',
        ],
        'success' => [
            'text' => 'text-emerald-600 dark:text-emerald-300',
            'iconBg' => 'bg-emerald-50 dark:bg-emerald-500/10',
            'border' => 'border-emerald-100/80 dark:border-emerald-500/20',
        ],
        'warning' => [
            'text' => 'text-amber-600 dark:text-amber-300',
            'iconBg' => 'bg-amber-50 dark:bg-amber-500/10',
            'border' => 'border-amber-100/80 dark:border-amber-500/20',
        ],
        'error' => [
            'text' => 'text-red-600 dark:text-red-300',
            'iconBg' => 'bg-red-50 dark:bg-red-500/10',
            'border' => 'border-red-100/80 dark:border-red-500/20',
        ],
        'info' => [
            'text' => 'text-blue-600 dark:text-sky-300',
            'iconBg' => 'bg-blue-50 dark:bg-sky-500/10',
            'border' => 'border-blue-100/80 dark:border-sky-500/20',
        ],
    ];

    $currentColor = $colors[$type] ?? $colors['info'];

    // Icon SVG definitions
    $icons = [
        'quantity' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
        'warning' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
        'oversell' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
        'vat' =>
            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
    ];

    $iconSvg = $icons[$icon] ?? null;
@endphp

<div class="p-3 rounded-xl border {{ $currentColor['border'] }} bg-white/90 shadow-sm shadow-slate-200/60 flex items-center justify-between transition-colors dark:bg-slate-800/80 dark:shadow-none dark:ring-1 dark:ring-white/5">
    <div class="flex flex-col justify-center flex-grow pl-4">
        <span class="text-slate-500 dark:text-slate-300 font-medium text-sm mb-1">{{ $title }}</span>

        <div class="font-bold text-slate-800 dark:text-slate-50 text-xl p-1 leading-none">
            @if ($value !== null)
                {{ $value }}
            @else
                {{ $slot }}
            @endif
        </div>

        @if ($description)
            <span class="text-slate-400 dark:text-slate-400 text-xs mt-1">{{ $description }}</span>
        @endif
    </div>
    @if ($iconSvg)
        <div class="flex-shrink-0">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center {{ $currentColor['text'] }} {{ $currentColor['iconBg'] }} ring-1 ring-inset ring-slate-900/5 dark:ring-white/10">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="w-6 h-6 stroke-current">
                    {!! $iconSvg !!}
                </svg>
            </div>
        </div>
    @endif
</div>
