<?php

namespace App\Extensions\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AdminPanel
{
    public function enabledFor(?Authenticatable $user): bool;

    public function dashboardRoute(): string;
}
