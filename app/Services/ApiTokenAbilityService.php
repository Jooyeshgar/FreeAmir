<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApiTokenAbilityService
{
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
        return $this->abilities()
            ->filter(fn (string $ability) => $user->can($ability) || $this->userHasWildcardFor($user, $ability))
            ->values();
    }

    private function userHasWildcardFor($user, string $ability): bool
    {
        $parts = explode('.', $ability);

        for ($i = count($parts) - 1; $i > 0; $i--) {
            $wildcard = implode('.', array_slice($parts, 0, $i)).'.*';
            if ($user->can($wildcard)) {
                return true;
            }
        }

        return false;
    }
}
