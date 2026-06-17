<?php

namespace App\Http\Middleware;

use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigLoader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $globals = Config::withoutGlobalScope(FiscalYearScope::class)->whereNull('company_id')->get();

            foreach ($globals->merge(Config::all()) as $config) {
                if ($config->value !== null) {
                    $this->apply($config);
                }
            }
        } catch (\Exception $exception) {
            //
        }

        return $next($request);
    }

    private function apply(Config $config): void
    {
        config(['amir.'.$config->key => $config->value]);
        if (str_starts_with($config->key, 'app_')) {
            $value = $config->key === 'app_debug' ? str($config->value)->toBoolean() : $config->value;
            config([str_replace('app_', 'app.', $config->key) => $value]);

            if ($config->key === 'app_locale') {
                app()->setLocale($config->value);
            }
        }
    }
}
