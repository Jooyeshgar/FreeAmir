<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use InvalidArgumentException;

/**
 * Enforces application feature switches that affect route access.
 *
 * The email-verification branch delegates to Laravel's middleware so its
 * normal redirect/JSON behavior is preserved.
 */
class EnsureFeatureEnabled extends EnsureEmailIsVerified
{
    public function handle($request, Closure $next, $feature = 'email_verification')
    {
        return match ($feature) {
            'registration' => $this->ensureRegistrationIsEnabled($request, $next),
            'email_verification' => $this->ensureEmailIsVerified($request, $next),
            default => throw new InvalidArgumentException("Unsupported application feature: {$feature}"),
        };
    }

    private function ensureRegistrationIsEnabled($request, callable $next)
    {
        if (! config('app.registration')) {
            return redirect()->route('login')->with('error', __('Registration is currently disabled.'));
        }

        return $next($request);
    }

    private function ensureEmailIsVerified($request, callable $next)
    {
        if (! config('app.email_verification')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
