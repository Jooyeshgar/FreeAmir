<?php

namespace Tests\Feature\Extensions;

use App\Extensions\MenuItem;
use App\Extensions\NavigationRegistry;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NavigationTreeTest extends TestCase
{
    private NavigationRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new NavigationRegistry($this->app);
    }

    public function test_children_can_be_registered_before_parents_and_sorted_at_every_level(): void
    {
        $child = new MenuItem('extension.categories', 'Categories', 'categories.index', 'core.management.system', priority: 150);
        $this->registry->register($child);
        $this->registry->register(new MenuItem('core.management.system.users', 'Users', 'users.index', 'core.management.system', priority: 200));
        $this->registry->register(new MenuItem('core.management.system', 'System', parent: 'core.management', priority: 300));
        $this->registry->register(new MenuItem('core.management', 'Management', priority: 900));
        $this->registry->register(new MenuItem('core.home', 'Home', 'home', priority: 100));

        $tree = $this->registry->tree();

        self::assertSame(['core.home', 'core.management'], $this->ids($tree));
        self::assertSame(['core.management.system'], $this->ids($tree[1]['children']));
        self::assertSame(['extension.categories', 'core.management.system.users'], $this->ids($tree[1]['children'][0]['children']));
        self::assertSame($child, $tree[1]['children'][0]['children'][0]['item']);
    }

    public function test_equal_priority_siblings_sort_by_id_regardless_of_registration_order(): void
    {
        $this->registry->register(new MenuItem('beta', 'Beta'));
        $this->registry->register(new MenuItem('alpha', 'Alpha'));

        self::assertSame(['alpha', 'beta'], $this->ids($this->registry->tree()));
    }

    public function test_reading_does_not_cache_the_tree_or_evaluate_request_callbacks(): void
    {
        $calls = 0;
        $visible = static function (array $context) use (&$calls): bool {
            $calls++;

            return $context['fiscal_year_id'] === 12;
        };
        $item = new MenuItem('core.root', 'Root', permissions: ['first', 'second'], permissionMode: 'all', visible: $visible);
        $this->registry->register($item);
        self::assertSame([], $this->registry->tree()[0]['children']);

        $this->registry->register(new MenuItem('extension.child', 'Child', parent: 'core.root'));
        $tree = $this->registry->tree();

        self::assertSame(['extension.child'], $this->ids($tree[0]['children']));
        self::assertSame(0, $calls);
        self::assertSame(['first', 'second'], $tree[0]['item']->permissions);
        self::assertSame('all', $tree[0]['item']->permissionMode);
        self::assertSame($visible, $tree[0]['item']->visible);
        self::assertTrue(($tree[0]['item']->visible)(['fiscal_year_id' => 12]));
        self::assertFalse(($tree[0]['item']->visible)(['fiscal_year_id' => 13]));
    }

    public function test_route_parameters_are_preserved_without_requiring_routes_to_exist_during_boot(): void
    {
        $item = new MenuItem('extension.service-buy', 'Buy Service', 'not-yet-booted.index', routeParameters: ['invoice_type' => 'buy', 'service_buy' => '1']);
        $this->registry->register($item);

        self::assertSame(['invoice_type' => 'buy', 'service_buy' => '1'], $this->registry->tree()[0]['item']->routeParameters);
        self::assertSame($item->routeParameters, $this->registry->items()[0]['route_parameters']);
    }

    public function test_unlabelled_definitions_retain_children_for_render_time_filtering(): void
    {
        $this->registry->register(new MenuItem('child', 'Child', parent: 'root'));
        $this->registry->register(new MenuItem('root'));

        $tree = $this->registry->tree();

        self::assertNull($tree[0]['item']->label);
        self::assertSame(['child'], $this->ids($tree[0]['children']));
    }

    public function test_unknown_parent_is_reported_on_read_and_can_be_registered_afterward(): void
    {
        $this->registry->register(new MenuItem('child', 'Child', parent: 'late-parent'));

        try {
            $this->registry->tree();
            self::fail('An unknown parent must be reported outside production.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('child', $exception->getMessage());
            self::assertStringContainsString('late-parent', $exception->getMessage());
        }

        $this->registry->register(new MenuItem('late-parent', 'Parent'));

        self::assertSame(['child'], $this->ids($this->registry->tree()[0]['children']));
    }

    public function test_production_omits_orphans_and_their_descendants_in_any_registration_order(): void
    {
        $this->app->instance('env', 'production');
        $this->registry->register(new MenuItem('grandchild', 'Grandchild', parent: 'orphan'));
        $this->registry->register(new MenuItem('orphan', 'Orphan', parent: 'missing'));
        $this->registry->register(new MenuItem('healthy', 'Healthy'));

        self::assertSame(['healthy'], $this->ids($this->registry->tree()));
        self::assertCount(3, $this->registry->items());
    }

    #[DataProvider('cyclicParents')]
    public function test_disconnected_cycles_are_reported_outside_production(array $parents): void
    {
        foreach ($parents as $id => $parent) {
            $this->registry->register(new MenuItem($id, $id, parent: $parent));
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Navigation parent cycle:');

        $this->registry->tree();
    }

    public static function cyclicParents(): array
    {
        return [
            'self-reference' => [['self' => 'self']],
            'two-node cycle' => [['first' => 'second', 'second' => 'first']],
            'descendant registered first' => [['child' => 'first', 'first' => 'second', 'second' => 'first']],
        ];
    }

    public function test_production_omits_cycles_and_their_descendants_but_keeps_valid_roots(): void
    {
        $this->app->instance('env', 'production');
        foreach (['child' => 'first', 'first' => 'second', 'second' => 'first'] as $id => $parent) {
            $this->registry->register(new MenuItem($id, $id, parent: $parent));
        }
        $this->registry->register(new MenuItem('healthy', 'Healthy'));

        self::assertSame(['healthy'], $this->ids($this->registry->tree()));
    }

    public function test_legacy_readers_keep_the_original_array_shape_and_metadata(): void
    {
        $visible = static fn (array $context): bool => $context['user'] !== null;
        $this->registry->register('legacy', [
            'area' => 'navigation.management',
            'label' => 'Legacy',
            'route' => 'legacy.index',
            'permission' => 'legacy.view',
            'visible' => $visible,
            'custom_metadata' => ['kept' => true],
        ]);
        $this->registry->register(new MenuItem('core.management', 'Management'));

        self::assertSame([[
            'area' => 'navigation.management',
            'label' => 'Legacy',
            'route' => 'legacy.index',
            'permission' => 'legacy.view',
            'visible' => $visible,
            'custom_metadata' => ['kept' => true],
            'id' => 'legacy',
            'priority' => 100,
        ]], $this->registry->items('navigation.management'));

        $item = $this->registry->tree()[0]['children'][0]['item'];
        self::assertSame('core.management', $item->parent);
        self::assertSame(['legacy.view'], $item->permissions);
        self::assertSame($visible, $item->visible);
    }

    #[DataProvider('legacyPlacements')]
    public function test_legacy_priorities_stay_inside_their_original_slot(string $area, string $parent, int $slotPriority): void
    {
        $this->registry->register('z-legacy', ['area' => $area, 'label' => 'Z', 'priority' => PHP_INT_MIN]);
        $this->registry->register('a-legacy', ['area' => $area, 'label' => 'A', 'priority' => PHP_INT_MIN]);
        $this->registry->register('last-legacy', ['area' => $area, 'label' => 'Last', 'priority' => PHP_INT_MAX]);
        $this->registry->register(new MenuItem('after-slot', 'After', parent: $parent, priority: $slotPriority + 50));
        $this->registry->register(new MenuItem('before-slot', 'Before', parent: $parent, priority: $slotPriority - 50));
        $this->registry->register(new MenuItem($parent, 'Parent'));

        self::assertSame(['before-slot', 'a-legacy', 'z-legacy', 'last-legacy', 'after-slot'], $this->ids($this->registry->tree()[0]['children']));
        self::assertSame(['a-legacy', 'z-legacy', 'last-legacy'], array_column($this->registry->items($area), 'id'));
    }

    public static function legacyPlacements(): array
    {
        return [
            'invoices end slot' => ['navigation.workspace.invoices', 'core.invoices', 700],
            'management before About slot' => ['navigation.management', 'core.management', 400],
        ];
    }

    public function test_duplicate_ids_are_rejected_between_typed_and_legacy_items(): void
    {
        $this->registry->register(new MenuItem('same', 'Typed'));

        $this->expectException(LogicException::class);
        $this->registry->register('same', ['area' => 'navigation.management']);
    }

    public function test_typed_items_cannot_replace_legacy_definitions_with_the_same_id(): void
    {
        $this->registry->register('same', ['area' => 'navigation.management']);

        $this->expectException(LogicException::class);
        $this->registry->register(new MenuItem('same', 'Typed'));
    }

    public function test_duplicate_legacy_ids_keep_the_original_duplicate_error_even_with_an_invalid_definition(): void
    {
        $this->registry->register('same', ['area' => 'navigation.management']);

        $this->expectException(LogicException::class);
        $this->registry->register('same', []);
    }

    public function test_typed_and_array_definitions_cannot_be_mixed_in_one_registration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry->register(new MenuItem('typed', 'Typed'), ['area' => 'navigation.management']);
    }

    #[DataProvider('invalidDefinitions')]
    public function test_invalid_typed_definitions_fail_before_registration(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MenuItem(...$arguments);
    }

    public static function invalidDefinitions(): array
    {
        return [
            'empty id' => [['id' => '']],
            'whitespace id' => [['id' => ' item ']],
            'empty parent' => [['id' => 'item', 'parent' => '']],
            'blank label' => [['id' => 'item', 'label' => ' ']],
            'URL instead of route' => [['id' => 'item', 'route' => 'https://example.com']],
            'path instead of route' => [['id' => 'item', 'route' => '/invoices']],
            'invalid permission mode' => [['id' => 'item', 'permissionMode' => 'some']],
            'empty permission' => [['id' => 'item', 'permissions' => ['']]],
            'non-string permission' => [['id' => 'item', 'permissions' => [12]]],
            'associative permissions' => [['id' => 'item', 'permissions' => ['permission' => 'view']]],
            'request object as parameter' => [['id' => 'item', 'route' => 'index', 'routeParameters' => ['request' => new \stdClass]]],
            'numeric parameter key' => [['id' => 'item', 'route' => 'index', 'routeParameters' => [1]]],
            'parameters without route' => [['id' => 'item', 'routeParameters' => ['invoice_type' => 'sell']]],
        ];
    }

    /** @param array<int, array{item: MenuItem, children: array}> $tree */
    private function ids(array $tree): array
    {
        return array_map(static fn (array $node): string => $node['item']->id, $tree);
    }
}
