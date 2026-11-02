<?php

namespace App\View\Components;

use App\Extensions\Contracts\HostContext;
use App\Extensions\MenuItem;
use App\Extensions\NavigationRegistry;
use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

class Menu extends Component
{
    /** @var array<int, array{item: MenuItem, children: array}> */
    public array $items = [];

    public function __construct(NavigationRegistry $registry, HostContext $hostContext)
    {
        $user = $hostContext->user();
        if ($user === null) {
            return;
        }

        $context = ['user' => $user, 'fiscal_year_id' => $hostContext->activeFiscalYearId()];
        $this->items = $this->filterTree($registry->tree(), $user, $context);
    }

    public function render(): View
    {
        return view('components.menu', ['menu' => $this]);
    }

    /** @param array<int, array{item: MenuItem, children: array}> $tree */
    private function filterTree(array $tree, object $user, array $context): array
    {
        $visible = [];

        foreach ($tree as $node) {
            $item = $node['item'];
            if (! $this->passesGuards($item, $user, $context)) {
                continue;
            }

            $children = $this->filterTree($node['children'], $user, $context);

            if ($item->label === null || ($item->route !== null && ! Route::has($item->route))) {
                continue;
            }

            if ($item->route === null && $children === []) {
                continue;
            }

            $visible[] = ['item' => $item, 'children' => $children];
        }

        return $visible;
    }

    private function passesGuards(MenuItem $item, object $user, array $context): bool
    {
        if ($item->permissions !== []) {
            $allowed = array_map(static fn (string $permission): bool => $user->can($permission), $item->permissions);
            if ($item->permissionMode === 'all' ? in_array(false, $allowed, true) : ! in_array(true, $allowed, true)) {
                return false;
            }
        }

        return $item->visible === null || ($item->visible instanceof Closure && ($item->visible)($context));
    }

    public function isActive(MenuItem $item): bool
    {
        if ($item->route === null || ! request()->routeIs($item->route)) {
            return false;
        }

        $parameters = $item->routeParameters;
        if ($item->route === 'invoices.index') {
            // These distinguish the menu variants; pagination and search filters do not.
            $parameters += ['invoice_type' => null, 'service_buy' => null];
        }

        // route() consumes path parameters before adding the remaining query
        // parameters. Match the same values, without allowing query overrides.
        $routeParameters = request()->route()?->parameters ?? [];
        $query = $routeParameters + request()->query();
        foreach ($parameters as $key => $expected) {
            if ($expected === null) {
                if (array_key_exists($key, $query)) {
                    return false;
                }

                continue;
            }

            $actual = $query[$key] ?? null;
            if ($actual instanceof UrlRoutable) {
                $actual = $actual->getRouteKey();
            }

            if (! array_key_exists($key, $query) || ! is_scalar($actual) || (string) $actual !== (string) $expected) {
                return false;
            }
        }

        return true;
    }

    /** @param array{item: MenuItem, children: array} $node */
    public function containsActive(array $node): bool
    {
        if ($this->isActive($node['item'])) {
            return true;
        }

        foreach ($node['children'] as $child) {
            if ($this->containsActive($child)) {
                return true;
            }
        }

        return false;
    }
}
