<?php

namespace App\Mcp\Auth;

use App\Models\User;
use Illuminate\Support\Str;

class AuthContext
{
    public function __construct(
        public readonly User $user,
        public readonly array $abilities,
    ) {}

    public function can(string $permission): bool
    {
        return $this->userCan($permission)
            && collect($this->abilities)->contains(fn (string $ability) => Str::is($ability, $permission));
    }

    public function userCan(string $permission): bool
    {
        return $this->user->can($permission);
    }
}
