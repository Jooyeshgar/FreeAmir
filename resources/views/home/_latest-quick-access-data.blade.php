@php
    $labelHeading = $labelHeading ?? __('Name');
    $invoiceTypeThemes = [
        'buy' => 'bg-info/10 text-info',
        'sell' => 'bg-success/10 text-success',
        'return_buy' => 'bg-warning/10 text-warning',
        'return_sell' => 'bg-warning/10 text-warning',
        'void' => 'bg-error/10 text-error',
        'documentable' => 'bg-primary/10 text-primary',
        'manual_document' => 'bg-base-300 text-base-content/60',
    ];
@endphp

<div class="mt-5 flex-1 overflow-hidden rounded-xl border border-base-300/70 bg-base-200/30" data-home-recent-list="{{ $area }}">
    @isset($description)
        <p class="text-center border-b border-base-300/70 px-3 py-2 text-xs leading-5 text-base-content/55">{{ $description }}</p>
    @endisset

    <table class="w-full table-fixed text-xs">
        <colgroup>
            <col class="w-[62%]">
            <col class="w-[38%]">
        </colgroup>
        <thead class="border-b border-base-300/70 text-[0.65rem] font-semibold text-base-content/50">
            <tr>
                <th scope="col" class="px-3 py-2 text-center">{{ $labelHeading }}</th>
                <th scope="col" class="px-1 py-2 text-start">{{ __('Date') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr class="border-b border-base-300/50 last:border-b-0" data-home-recent-row>
                    <td class="px-3 py-2">
                        <div class="flex min-w-0 items-center gap-1.5">
                            @if (isset($item['type'], $item['typeKey']))
                                @if (! empty($item['typeHref']))
                                    <a href="{{ $item['typeHref'] }}" aria-label="{{ $item['type'] }}" title="{{ $item['type'] }}"
                                        class="inline-flex size-6 shrink-0 items-center justify-center rounded-md transition hover:ring-2 hover:ring-current/20 {{ $invoiceTypeThemes[$item['typeKey']] ?? 'bg-base-300 text-base-content/60' }}">
                                @else
                                    <span role="img" aria-label="{{ $item['type'] }}" title="{{ $item['type'] }}"
                                        class="inline-flex size-6 shrink-0 items-center justify-center rounded-md {{ $invoiceTypeThemes[$item['typeKey']] ?? 'bg-base-300 text-base-content/60' }}">
                                @endif
                                    @switch($item['typeKey'])
                                        @case('buy')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14" />
                                            </svg>
                                            @break
                                        @case('sell')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21V9m0 0 4 4m-4-4-4 4M5 4h14" />
                                            </svg>
                                            @break
                                        @case('return_buy')
                                        @case('return_sell')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 @if ($item['typeKey'] === 'return_sell') -scale-x-100 @endif" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 7-4 4 4 4m-4-4h9a5 5 0 0 1 5 5v2" />
                                            </svg>
                                            @break
                                        @case('void')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m7 7 10 10M17 7 7 17" />
                                            </svg>
                                            @break
                                        @case('documentable')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 13a5 5 0 0 0 7.1.1l2-2A5 5 0 0 0 12 4l-1.1 1.1M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1" />
                                            </svg>
                                            @break
                                        @case('manual_document')
                                            <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 3h8l4 4v14H6V3Zm8 0v5h4M9 13h6M9 17h4" />
                                            </svg>
                                            @break
                                    @endswitch
                                @if (! empty($item['typeHref']))
                                    </a>
                                @else
                                    </span>
                                @endif
                            @endif

                            @if (isset($item['href']))
                                <a href="{{ $item['href'] }}" class="min-w-0 flex-1 truncate font-medium text-primary transition hover:underline" title="{{ $item['label'] }}">{{ $item['label'] }}</a>
                            @else
                                <span class="min-w-0 flex-1 truncate font-medium text-base-content" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-1 py-2">
                        <time class="block truncate whitespace-nowrap text-base-content/55" @if ($item['date']) datetime="{{ $item['date']->toDateString() }}" @endif>
                            {{ $item['date'] ? formatDate($item['date']) : '—' }}
                        </time>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-3 py-6 text-center text-xs text-base-content/45">{{ __('No data') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
