<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApiTokenAbilityService
{
    /**
     * Permissions attached to API routes are the abilities users may grant to tokens.
     */
    public function abilities(): Collection
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
            ->flatMap(fn ($route) => collect($route->gatherMiddleware())
                ->filter(fn (string $middleware) => str_starts_with($middleware, 'check-permission:'))
                ->map(fn (string $middleware) => substr($middleware, strlen('check-permission:'))))
            ->reject(fn (string $permission) => in_array($permission, [
                'api.access',
                'api-tokens.index',
                'api-tokens.store',
                'api-tokens.destroy',
            ], true))
            ->unique()
            ->sort()
            ->values();
    }

    public function userAbilities($user): Collection
    {
        $apiAbilities = $this->abilities()->all();

        return $user->getAllPermissions()
            ->whereIn('name', $apiAbilities)
            ->sortBy('name')
            ->values();
    }
}
