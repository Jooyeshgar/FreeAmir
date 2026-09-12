@props(['source'])

@php
    $publicPath = preg_replace('/\.md$/i', '.html', ltrim($source, '/'));
    $guideUrl = rtrim((string) config('app.user_guide_url'), '/').'/'.$publicPath;
@endphp

<a href="{{ $guideUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="{{ __('Help') }}"
    title="{{ __('Help') }}"
    data-user-guide-link
    {{ $attributes->class(['btn btn-ghost btn-circle btn-sm shrink-0 text-info hover:bg-info/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-info']) }}>
    <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.1 9a3 3 0 1 1 5.83 1c0 2-2.93 2-2.93 4m0 3h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
</a>
