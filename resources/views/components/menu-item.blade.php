@php
    /** @var \App\Extensions\MenuItem $item */
    $item = $node['item'];
    $hasChildren = $node['children'] !== [];
    $active = $menu->containsActive($node);
    $isTopLevel = $depth === 0;
    $topDropdownClass = 'app-main-menu-dropdown';
    $topDropdownContentClass = 'app-main-menu-panel z-50 w-full min-w-full max-w-[calc(100vw-2rem)] overflow-y-auto md:w-50';
    if ($isTopLevel && $item->id === 'core.management') {
        $topDropdownContentClass .= ' max-h-[calc(100dvh-8rem)]';
    }
@endphp

<li>
    @if ($hasChildren)
        <details @class([$topDropdownClass => $isTopLevel]) @if ($isTopLevel) data-main-menu-dropdown @endif>
            <summary @class(['menu-active' => $active])>{{ __($item->label) }}</summary>
            <ul @class([$topDropdownContentClass => $isTopLevel])>
                @foreach ($node['children'] as $child)
                    @include('components.menu-item', ['node' => $child, 'depth' => $depth + 1, 'menu' => $menu])
                @endforeach
            </ul>
        </details>
    @elseif ($item->route !== null)
        <a href="{{ route($item->route, $item->routeParameters) }}" @class(['menu-active' => $menu->isActive($item)]) @if ($menu->isActive($item)) aria-current="page" @endif>
            {{ __($item->label) }}
        </a>
    @endif
</li>
