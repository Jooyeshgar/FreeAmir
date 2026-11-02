@foreach ($items as $node)
    @include('components.menu-item', ['node' => $node, 'depth' => 0, 'menu' => $menu])
@endforeach
