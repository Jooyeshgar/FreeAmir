<?php

namespace App\Extensions;

use Closure;
use InvalidArgumentException;

/**
 * Immutable definition of one core or extension navigation item.
 */
final readonly class MenuItem
{
    /**
     * @param  array<int, string>  $permissions
     * @param  array<string, scalar|null>  $routeParameters
     */
    public function __construct(
        public string $id,
        public ?string $label = null,
        public ?string $route = null,
        public ?string $parent = null,
        public int $priority = 100,
        public array $permissions = [],
        public string $permissionMode = 'any',
        public ?Closure $visible = null,
        public array $routeParameters = [],
    ) {
        if ($this->id === '' || trim($this->id) !== $this->id) {
            throw new InvalidArgumentException('Navigation item IDs must be non-empty and have no surrounding whitespace.');
        }

        if ($this->parent !== null && ($this->parent === '' || trim($this->parent) !== $this->parent)) {
            throw new InvalidArgumentException('Navigation parents must be null or non-empty IDs without surrounding whitespace.');
        }

        if ($this->label !== null && trim($this->label) === '') {
            throw new InvalidArgumentException('Navigation labels must be null or non-empty translation keys.');
        }

        if ($this->route !== null && (trim($this->route) === '' || str_starts_with($this->route, '/') || str_contains($this->route, '://'))) {
            throw new InvalidArgumentException('Navigation routes must be named routes.');
        }

        if (! in_array($this->permissionMode, ['any', 'all'], true)) {
            throw new InvalidArgumentException('Navigation permission mode must be either [any] or [all].');
        }

        if (! array_is_list($this->permissions)) {
            throw new InvalidArgumentException('Navigation permissions must be a list.');
        }

        foreach ($this->permissions as $permission) {
            if (! is_string($permission) || trim($permission) === '') {
                throw new InvalidArgumentException('Navigation permissions must be non-empty strings.');
            }
        }

        foreach ($this->routeParameters as $key => $value) {
            if (! is_string($key) || $key === '' || (! is_scalar($value) && $value !== null)) {
                throw new InvalidArgumentException('Navigation route parameters must have string keys and scalar or null values.');
            }
        }

        if ($this->route === null && $this->routeParameters !== []) {
            throw new InvalidArgumentException('Navigation route parameters require a named route.');
        }
    }

    /**
     * Expose definitions without evaluating permissions or visibility.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'route' => $this->route,
            'parent' => $this->parent,
            'priority' => $this->priority,
            'permissions' => $this->permissions,
            'permission_mode' => $this->permissionMode,
            'visible' => $this->visible,
            'route_parameters' => $this->routeParameters,
        ];
    }
}
