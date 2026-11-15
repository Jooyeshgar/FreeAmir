<?php

namespace App\Mcp\Auth;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

class TokenAuthenticator
{
    public function authenticate(#[\SensitiveParameter] ?string $tokenString): ?AuthContext
    {
        if (! $tokenString) {
            return null;
        }

        $model = Sanctum::$personalAccessTokenModel;
        $token = $model::findToken($tokenString);
        if (! $token || ($token->expires_at && $token->expires_at->isPast())
            || (config('sanctum.expiration') && $token->created_at->lte(now()->subMinutes(config('sanctum.expiration'))))
            || ! $token->tokenable instanceof User) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return new AuthContext($token->tokenable, $token->abilities ?? []);
    }
}
