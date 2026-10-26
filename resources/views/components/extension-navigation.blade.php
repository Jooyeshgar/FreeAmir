@foreach ($items as $item)
    <li>
        <a href="{{ route($item['route']) }}"
            @if (request()->routeIs($item['route'])) aria-current="page" @endif>
            {{ __($item['label']) }}
        </a>
    </li>
@endforeach
