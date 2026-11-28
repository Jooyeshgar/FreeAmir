@props([
    'title',
    'description' => null,
    'tip' => null,
    'prerequisites' => [],
    'prerequisitesTitle' => null,
    'actionLabel' => null,
    'actionUrl' => null,
    'fiscalYearClosed' => false,
    'guideSource' => null,
    'compact' => false,
    'filtered' => false,
    'filteredTitle' => null,
    'resetUrl' => null,
])

@php
    $guideUrl = null;
    if ($guideSource && config('app.user_guide_url')) {
        $localizedSource = app()->getLocale() === 'en'
            ? str_replace('.md', '.en.md', ltrim($guideSource, '/'))
            : ltrim($guideSource, '/');
        $publicPath = preg_replace('/\.md$/i', '.html', $localizedSource);
        $guideUrl = rtrim((string) config('app.user_guide_url'), '/') . '/' . $publicPath;
    }

    $hasIncompletePrerequisites = collect($prerequisites)->contains(fn (array $item) => empty($item['done']));
    $hasFooterActions = ($actionLabel && $actionUrl) || isset($actions) || $guideUrl;
@endphp

<div
    data-empty-state="{{ $filtered ? 'filtered' : 'onboarding' }}"
    {{ $attributes->class([
        'flex flex-col items-center justify-center rounded-2xl border border-dashed border-base-300 bg-base-200/35 text-center',
        $compact ? 'px-4 py-4 sm:px-5 sm:py-5' : 'px-6 py-8',
    ]) }}
>
    @if ($filtered)
        <span class="mb-2.5 inline-flex size-11 items-center justify-center rounded-2xl bg-base-200 text-base-content/45">
            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
            </svg>
        </span>

        <h3 class="text-sm font-bold text-base-content/80 sm:text-base">
            {{ $filteredTitle ?? __('No matching records found.') }}
        </h3>
        <p class="mt-1 max-w-md text-xs text-base-content/55 sm:text-sm">
            {{ __('Try adjusting your search filters.') }}
        </p>

        @if ($resetUrl)
            <div class="mt-3">
                <a href="{{ $resetUrl }}" class="btn btn-xs btn-ghost border border-base-300 bg-base-100 sm:btn-sm">
                    {{ __('Clear filters') }}
                </a>
            </div>
        @endif
    @else
        @if ($compact)
            <div class="flex flex-wrap items-center justify-center gap-2">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-info/12 text-info ring-1 ring-info/20">
                    @isset($icon)
                        {{ $icon }}
                    @else
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    @endisset
                </span>
                <h3 class="text-sm font-bold text-base-content sm:text-base">
                    {{ $title }}
                </h3>
            </div>
        @else
            <span class="mb-3 inline-flex size-12 items-center justify-center rounded-2xl bg-info/12 text-info ring-1 ring-info/20">
                @isset($icon)
                    {{ $icon }}
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                @endisset
            </span>

            <h3 class="text-base font-bold text-base-content">
                {{ $title }}
            </h3>
        @endif

        @if ($description)
            <p class="{{ $compact ? 'mt-1 text-xs leading-5' : 'mt-1.5 text-sm leading-6' }} max-w-lg text-base-content/65">
                {{ $description }}
            </p>
        @endif

        @if (! empty($prerequisites))
            <div class="{{ $compact ? 'mt-3 p-2.5 sm:p-3' : 'mt-4 p-3.5' }} w-full max-w-xl rounded-2xl border border-base-300 bg-base-100 text-start shadow-xs" data-empty-state-prerequisites>
                @if ($prerequisitesTitle)
                    <p class="mb-2 text-xs font-bold text-base-content/75">
                        {{ $prerequisitesTitle }}
                    </p>
                @endif

                <div class="divide-y divide-base-200">
                    @foreach ($prerequisites as $prereq)
                        @php
                            $isDone = ! empty($prereq['done']);
                        @endphp
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 py-2 first:pt-0 last:pb-0"
                            data-prerequisite-item="{{ $prereq['key'] ?? $loop->index }}" data-prerequisite-done="{{ $isDone ? '1' : '0' }}"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                @if ($isDone)
                                    <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </span>
                                @else
                                    <span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-info/15 text-[11px] font-bold text-info">
                                        {{ formatNumber($loop->iteration) }}
                                    </span>
                                @endif
                                <span class="text-xs font-semibold {{ $isDone ? 'text-base-content/50 line-through' : 'text-base-content' }}">
                                    {{ $prereq['title'] }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                @if ($isDone)
                                    <span class="badge badge-xs border-emerald-500/25 bg-emerald-500/10 py-2 font-medium text-emerald-700 dark:text-emerald-300">
                                        {{ __('Completed') }}
                                    </span>
                                @elseif (! empty($prereq['actions']))
                                    @foreach ($prereq['actions'] as $action)
                                        <x-closed-year-action-link
                                            :fiscal-year-closed="$fiscalYearClosed"
                                            href="{{ $action['url'] }}"
                                            class="btn btn-xs {{ $loop->first ? 'btn-info' : 'btn-ghost border border-base-300 bg-base-100 hover:border-info/40 hover:bg-info/10 hover:text-info' }} gap-1 font-medium"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            <span>{{ $action['label'] }}</span>
                                        </x-closed-year-action-link>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($tip)
            <div class="{{ $compact ? 'mt-2.5 px-3 py-1.5' : 'mt-3 px-3.5 py-2.5' }} flex max-w-xl items-start gap-2 rounded-xl border border-info/20 bg-info/8 text-start text-xs leading-5 text-base-content/75">
                <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 size-4 shrink-0 text-info" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <span>{{ $tip }}</span>
            </div>
        @endif

        @if ($hasFooterActions)
            <div class="{{ $compact ? 'mt-3' : 'mt-4' }} flex flex-wrap items-center justify-center gap-2">
                @if ($actionLabel && $actionUrl)
                    <x-closed-year-action-link
                        :fiscal-year-closed="$fiscalYearClosed"
                        href="{{ $actionUrl }}"
                        class="btn btn-sm gap-1.5 {{ $hasIncompletePrerequisites ? 'btn-outline btn-primary' : 'btn-primary' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>{{ $actionLabel }}</span>
                    </x-closed-year-action-link>
                @endif

                @isset($actions)
                    {{ $actions }}
                @endisset

                @if ($guideUrl)
                    <a href="{{ $guideUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm gap-1.5 text-info hover:bg-info/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                        </svg>
                        <span>{{ __('Read guide') }}</span>
                    </a>
                @endif
            </div>
        @endif
    @endif
</div>
