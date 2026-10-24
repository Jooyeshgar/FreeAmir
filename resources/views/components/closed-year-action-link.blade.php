@props(['href', 'fiscalYearClosed'])

@php($closedYearReason = __('This fiscal year is already closed.'))

@if ($fiscalYearClosed)
    @php($descriptionId = 'closed-year-action-' . \Illuminate\Support\Str::uuid())
    <a role="link" aria-disabled="true" tabindex="-1" title="{{ $closedYearReason }}" aria-describedby="{{ $descriptionId }}"
        {{ $attributes->class(['cursor-not-allowed opacity-60']) }}>
        {{ $slot }}
        <span id="{{ $descriptionId }}" class="sr-only">{{ $closedYearReason }}</span>
    </a>
@else
    <a href="{{ $href }}" {{ $attributes }}>{{ $slot }}</a>
@endif
