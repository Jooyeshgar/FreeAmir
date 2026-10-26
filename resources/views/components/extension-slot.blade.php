@foreach ($items as $item)
    @include($item['view'], $item['data'])
@endforeach
