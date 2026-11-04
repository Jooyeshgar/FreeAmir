<?php

namespace App\Extensions;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use LogicException;

final class NavigationRegistry
{
    /**
     * Legacy priorities order extensions inside their original insertion slot.
     * These slot priorities are independent of each extension's own priority.
     *
     * @var array<string, array{parent: string, priority: int}>
     */
    private const LEGACY_PLACEMENTS = [
        'navigation.workspace.invoices' => ['parent' => 'core.invoices', 'priority' => 700],
        'navigation.management' => ['parent' => 'core.management', 'priority' => 400],
    ];

    /** @var array<string, MenuItem|array<string, mixed>> */
    private array $items = [];

    public function __construct(private readonly Application $application) {}

    /**
     * Register a typed definition, or retain the version 1 array API verbatim.
     *
     * @param  array<string, mixed>|null  $definition
     */
    public function register(string|MenuItem $id, ?array $definition = null): void
    {
        $key = $id instanceof MenuItem ? $id->id : trim($id);
        if ($key === '') {
            throw new InvalidArgumentException('Navigation item IDs cannot be empty.');
        }

        if (isset($this->items[$key])) {
            throw new LogicException("Navigation item [{$key}] is already registered.");
        }

        if ($id instanceof MenuItem) {
            if ($definition !== null) {
                throw new InvalidArgumentException('A MenuItem must be registered without a separate definition.');
            }

            $this->items[$key] = $id;

            return;
        }

        $area = trim((string) ($definition['area'] ?? ''));
        if ($area === '') {
            throw new InvalidArgumentException("Navigation item [{$key}] must define an area.");
        }

        $this->items[$key] = [
            ...$definition,
            'id' => $key,
            'area' => $area,
            'priority' => (int) ($definition['priority'] ?? 100),
        ];
    }

    /**
     * Version 1 array readers keep their original fields, area filtering and order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function items(?string $area = null): array
    {
        $items = array_map(
            static fn (MenuItem|array $item): array => $item instanceof MenuItem ? $item->toArray() : $item,
            array_values($this->items),
        );

        if ($area !== null) {
            $items = array_values(array_filter($items, static fn (array $item): bool => ($item['area'] ?? null) === $area));
        }

        usort($items, static fn (array $left, array $right): int => [$left['priority'], $left['id']] <=> [$right['priority'], $right['id']]);

        return $items;
    }

    /**
     * Assemble definitions at read time, without retaining request-specific state.
     * Parent validation includes disconnected cycles and descendants of orphans.
     *
     * @return array<int, array{item: MenuItem, children: array}>
     */
    public function tree(): array
    {
        $items = [];
        foreach ($this->items as $id => $definition) {
            try {
                $items[$id] = $definition instanceof MenuItem ? $definition : $this->legacyItem($definition);
            } catch (InvalidArgumentException $exception) {
                $this->invalidStructure("Navigation item [{$id}] is invalid: {$exception->getMessage()}");
            }
        }

        $valid = [];
        $children = [];
        foreach ($items as $id => $item) {
            if ($this->validAncestry($id, $items, $valid, [])) {
                $children[$item->parent ?? ''][] = $item;
            }
        }

        foreach ($children as &$siblings) {
            usort($siblings, fn (MenuItem $left, MenuItem $right): int => $this->sortKey($left) <=> $this->sortKey($right));
        }
        unset($siblings);

        return $this->buildTree($children, '');
    }

    /** @param array<string, mixed> $definition */
    private function legacyItem(array $definition): MenuItem
    {
        $visible = $definition['visible'] ?? null;

        return new MenuItem(
            id: $definition['id'],
            label: is_string($definition['label'] ?? null) && $definition['label'] !== '' ? $definition['label'] : null,
            route: is_string($definition['route'] ?? null) && $definition['route'] !== '' ? $definition['route'] : null,
            parent: self::LEGACY_PLACEMENTS[$definition['area']]['parent'] ?? $definition['area'],
            priority: $definition['priority'],
            permissions: isset($definition['permission']) ? [$definition['permission']] : [],
            visible: $visible === null || $visible instanceof Closure ? $visible : static fn (array $context): bool => false,
        );
    }

    /**
     * @param  array<string, MenuItem>  $items
     * @param  array<string, bool>  $valid
     * @param  array<int, string>  $ancestors
     */
    private function validAncestry(string $id, array $items, array &$valid, array $ancestors): bool
    {
        if (array_key_exists($id, $valid)) {
            return $valid[$id];
        }

        if (in_array($id, $ancestors, true)) {
            $this->invalidStructure('Navigation parent cycle: '.implode(' -> ', [...$ancestors, $id]).'.');

            return $valid[$id] = false;
        }

        $parent = $items[$id]->parent;
        if ($parent === null) {
            return $valid[$id] = true;
        }

        if (! isset($items[$parent])) {
            $this->invalidStructure("Navigation item [{$id}] references unknown parent [{$parent}].");

            return $valid[$id] = false;
        }

        return $valid[$id] = $this->validAncestry($parent, $items, $valid, [...$ancestors, $id]);
    }

    private function invalidStructure(string $message): void
    {
        if (! $this->application->environment('production')) {
            throw new LogicException($message);
        }
    }

    /** @return array{int, int, int, string} */
    private function sortKey(MenuItem $item): array
    {
        $definition = $this->items[$item->id];
        $placement = is_array($definition) ? (self::LEGACY_PLACEMENTS[$definition['area']] ?? null) : null;

        return $placement === null
            ? [$item->priority, 0, 0, $item->id]
            : [$placement['priority'], 1, $item->priority, $item->id];
    }

    /**
     * @param  array<string, array<int, MenuItem>>  $children
     * @return array<int, array{item: MenuItem, children: array}>
     */
    private function buildTree(array $children, string $parent): array
    {
        return array_map(fn (MenuItem $item): array => [
            'item' => $item,
            'children' => $this->buildTree($children, $item->id),
        ], $children[$parent] ?? []);
    }
}
