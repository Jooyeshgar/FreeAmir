<?php

namespace Tests\Feature\Extensions;

use App\Extensions\NavigationRegistry;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class ExtensionRegistryTest extends TestCase
{
    public function test_multiple_navigation_items_have_stable_ordering_and_area_isolation(): void
    {
        $registry = app(NavigationRegistry::class);
        $registry->register('later', ['area' => 'management', 'priority' => 20]);
        $registry->register('beta', ['area' => 'management', 'priority' => 10]);
        $registry->register('alpha', ['area' => 'management', 'priority' => 10]);
        $registry->register('workspace', ['area' => 'workspace', 'priority' => 1]);
        self::assertSame(['alpha', 'beta', 'later'], array_column($registry->items('management'), 'id'));
        self::assertSame(['workspace'], array_column($registry->items('workspace'), 'id'));
    }

    public function test_duplicate_navigation_ids_are_rejected_across_areas(): void
    {
        $registry = app(NavigationRegistry::class);
        $registry->register('same', ['area' => 'management']);
        $this->expectException(LogicException::class);
        $registry->register('same', ['area' => 'workspace']);
    }

    public function test_navigation_requires_an_area(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(NavigationRegistry::class)->register('example', []);
    }
}
