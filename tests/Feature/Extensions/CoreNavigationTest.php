<?php

namespace Tests\Feature\Extensions;

use App\Extensions\Contracts\HostContext;
use App\Extensions\MenuItem;
use App\Extensions\NavigationRegistry;
use App\Models\User;
use App\View\Components\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CoreNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_provider_registers_the_complete_stable_tree_and_menu_variants(): void
    {
        $registry = app(NavigationRegistry::class);
        $tree = $registry->tree();
        $nodes = $this->flatten($tree);

        self::assertCount(84, $nodes);
        self::assertCount(70, array_filter($nodes, static fn (MenuItem $item): bool => $item->route !== null));
        self::assertCount(14, array_filter($nodes, static fn (MenuItem $item): bool => $item->route === null));
        foreach ($nodes as $item) {
            if ($item->route !== null) {
                self::assertTrue(Route::has($item->route), "Core navigation route [{$item->route}] must exist.");
            }
        }
        self::assertSame([
            'core.home', 'core.portal', 'core.invoices', 'core.accounting', 'core.reports',
            'core.warehouse', 'core.hr', 'core.crm', 'core.management',
        ], array_map(static fn (array $node): string => $node['item']->id, $tree));

        $variants = array_values(array_filter($nodes, static fn (MenuItem $item): bool => $item->route === 'invoices.index'));
        self::assertSame([
            ['invoice_type' => 'sell'], ['invoice_type' => 'return_sell'], ['invoice_type' => 'void'],
            ['invoice_type' => 'buy'], ['invoice_type' => 'buy', 'service_buy' => '1'],
            ['invoice_type' => 'return_buy'], ['invoice_type' => 'return_buy', 'service_buy' => '1'],
            ['invoice_type' => 'beginning_inventory'],
        ], array_map(static fn (MenuItem $item): array => $item->routeParameters, $variants));

        self::assertSame('core.management.hr-organization', $this->find($nodes, 'core.management.hr-organization.payrolls')->parent);
        self::assertSame('core.hr', $this->find($nodes, 'core.hr.payrolls')->parent);
    }

    public function test_rendering_filters_permissions_per_request_and_keeps_legacy_package_slots(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('home.*', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('invoice-categories.view', 'web'));
        $this->actingAs($user);

        $registry = app(NavigationRegistry::class);
        $registry->register('test.category', [
            'area' => 'navigation.workspace.invoices',
            'label' => 'Test category',
            'route' => 'invoices.index',
            'permission' => 'invoice-categories.view',
        ]);

        $html = Blade::render('<ul><x-menu /></ul>');

        self::assertStringContainsString('صفحه اصلی', $html);
        self::assertStringNotContainsString('فاکتورها', $html);
        self::assertStringNotContainsString('Test category', $html);
    }

    public function test_legacy_invoice_area_remains_after_core_children_when_parent_is_allowed(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('invoices.index', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('package.view', 'web'));
        $this->actingAs($user);

        app(NavigationRegistry::class)->register('package-category', [
            'area' => 'navigation.workspace.invoices',
            'label' => 'Package category',
            'route' => 'invoices.index',
            'permission' => 'package.view',
        ]);

        $html = Blade::render('<ul><x-menu /></ul>');

        self::assertStringContainsString('Package category', $html);
        self::assertLessThan(strpos($html, 'Package category'), strpos($html, 'Beginning Inventory'));
    }

    public function test_legacy_visibility_callback_receives_the_current_host_context(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('configs.index', 'web'));
        $this->actingAs($user);
        config(['active-fiscal-year-id' => 12]);

        app(NavigationRegistry::class)->register('context-category', [
            'area' => 'navigation.management',
            'label' => 'Context category',
            'route' => 'about',
            'visible' => static fn (array $context): bool => $context['fiscal_year_id'] === 12,
        ]);

        self::assertStringContainsString('Context category', Blade::render('<ul><x-menu /></ul>'));

        config(['active-fiscal-year-id' => 13]);
        self::assertStringNotContainsString('Context category', Blade::render('<ul><x-menu /></ul>'));
    }

    public function test_invoice_menu_variants_use_exact_discriminator_parameters_for_active_state(): void
    {
        $request = request();
        $request->setRouteResolver(static fn () => Route::getRoutes()->getByName('invoices.index'));
        $request->query->replace(['invoice_type' => 'buy', 'service_buy' => '1', 'search' => 'tea', 'page' => 2]);
        $menu = new Menu(app(NavigationRegistry::class), app(HostContext::class));
        $buy = new MenuItem('buy', 'Buy', 'invoices.index', routeParameters: ['invoice_type' => 'buy']);
        $buyService = new MenuItem('buy-service', 'Buy Service', 'invoices.index', routeParameters: ['invoice_type' => 'buy', 'service_buy' => '1']);

        self::assertFalse($menu->isActive($buy));
        self::assertTrue($menu->isActive($buyService));

        $request->query->remove('service_buy');
        self::assertTrue($menu->isActive($buy));
        self::assertFalse($menu->isActive($buyService));
    }

    /** @param array<int, array{item: MenuItem, children: array}> $tree
     * @return array<int, MenuItem>
     */
    private function flatten(array $tree): array
    {
        $items = [];
        foreach ($tree as $node) {
            $items[] = $node['item'];
            array_push($items, ...$this->flatten($node['children']));
        }

        return $items;
    }

    /** @param array<int, MenuItem> $items */
    private function find(array $items, string $id): MenuItem
    {
        foreach ($items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        self::fail("Missing navigation item [$id].");
    }
}
