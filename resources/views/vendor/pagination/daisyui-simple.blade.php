@if ($paginator->hasPages())
    <div class="join">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <button class="join-item btn btn-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                <span aria-hidden="true">&laquo;</span>
            </button>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="join-item btn" rel="prev" aria-label="@lang('pagination.previous')">
                &laquo;
            </a>
        @endif

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="join-item btn" rel="next" aria-label="@lang('pagination.next')">
                &raquo;
            </a>
        @else
            <button class="join-item btn btn-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                <span aria-hidden="true">&raquo;</span>
            </button>
        @endif
    </div>
@endif
