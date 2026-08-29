@props([
    'padded' => true,
    'variant' => 'default',
])

<article
    {{ $attributes->class([
        'w-full min-w-0 rounded-2xl shadow-sm',
        'border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900' => $variant === 'default',
        'grid-paper bg-[#15263b] text-white shadow-lg' => $variant === 'dark',
        'p-4 sm:p-6' => $padded,
    ]) }}
>
    {{ $slot }}
</article>
