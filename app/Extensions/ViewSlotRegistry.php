<?php

namespace App\Extensions;

use Closure;
use InvalidArgumentException;
use LogicException;

final class ViewSlotRegistry
{
    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private array $slots = [];

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>|null  $data
     */
    public function register(
        string $slot,
        string $id,
        string $view,
        ?Closure $data = null,
        int $priority = 100,
    ): void {
        $slot = trim($slot);
        $id = trim($id);
        $view = trim($view);

        if ($slot === '' || $id === '' || $view === '') {
            throw new InvalidArgumentException('View slots require a slot, item ID, and view name.');
        }

        if (isset($this->slots[$slot][$id])) {
            throw new LogicException("View slot item [{$slot}.{$id}] is already registered.");
        }

        $this->slots[$slot][$id] = [
            'id' => $id,
            'slot' => $slot,
            'view' => $view,
            'data' => $data,
            'priority' => $priority,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function items(string $slot): array
    {
        $items = array_values($this->slots[$slot] ?? []);

        usort($items, static function (array $left, array $right): int {
            return [$left['priority'], $left['id']] <=> [$right['priority'], $right['id']];
        });

        return $items;
    }
}
