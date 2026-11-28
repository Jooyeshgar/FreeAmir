<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class ApiTokenAbilityService
{
    /**
     * Single ability that matches every permission. The token still never exceeds
     * its owner's current permissions, because both layers are always checked.
     */
    public const FULL_ACCESS = '*';

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
            ->concat([
                'invoices.index', 'invoices.show', 'invoices.approve',
                'reports.cost-income', 'reports.result',
                'reports.company-overview', 'reports.trial-balance', 'reports.ledger',
                'customers.index', 'customers.show',
                'products.index', 'products.show',
                'customers.store', 'products.store', 'invoices.store',
                'products.edit', 'invoices.edit',
            ])
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
            ->filter(fn (string $ability) => $user->can($ability))
            ->values();
    }

    /**
     * Abilities the user may select on the token form: their own abilities plus full access.
     */
    public function grantableAbilities($user): Collection
    {
        return $this->userAbilities($user)->push(self::FULL_ACCESS)->values();
    }

    /**
     * Full access replaces every individual ability.
     *
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    public function normalize(array $abilities): array
    {
        return in_array(self::FULL_ACCESS, $abilities, true)
            ? [self::FULL_ACCESS]
            : array_values($abilities);
    }
}
