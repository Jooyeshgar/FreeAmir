<?php

namespace App\Extensions\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/** Optional platform administration supplied by an installed extension. */
interface AdminPanel
{
    public function enabledFor(?Authenticatable $user): bool;

    public function dashboardRoute(): string;
}
