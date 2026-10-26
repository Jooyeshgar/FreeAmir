<?php

namespace App\Extensions;

use InvalidArgumentException;
use LogicException;

final class NavigationRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $items = [];

    /**
     * @param  array<string, mixed>  $definition
     */
    public function register(string $id, array $definition): void
    {
        $id = trim($id);

        if ($id === '') {
            throw new InvalidArgumentException('Navigation item IDs cannot be empty.');
        }

        if (isset($this->items[$id])) {
            throw new LogicException("Navigation item [{$id}] is already registered.");
        }

        $area = trim((string) ($definition['area'] ?? ''));
        if ($area === '') {
            throw new InvalidArgumentException("Navigation item [{$id}] must define an area.");
        }

        $this->items[$id] = [
            ...$definition,
            'id' => $id,
            'area' => $area,
            'priority' => (int) ($definition['priority'] ?? 100),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(?string $area = null): array
    {
        $items = array_values($this->items);

        if ($area !== null) {
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => $item['area'] === $area,
            ));
        }

        usort($items, static function (array $left, array $right): int {
            return [$left['priority'], $left['id']] <=> [$right['priority'], $right['id']];
        });

        return $items;
    }
}
