@if ($paginator->hasPages())
    <div class="flex justify-center">
        <div class="join">
            @if ($paginator->onFirstPage())
                <button class="join-item btn btn-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                    <span aria-hidden="true">&laquo;</span>
                </button>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="join-item btn" rel="prev" aria-label="@lang('pagination.previous')">
                    &laquo;
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <button class="join-item btn btn-disabled" aria-disabled="true">{{ $element }}</button>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <button class="join-item btn btn-active" aria-current="page">{{ $page }}</button>
                        @else
                            <a href="{{ $url }}" class="join-item btn">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

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
    </div>
@endif
