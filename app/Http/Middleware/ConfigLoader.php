<?php

namespace App\Http\Middleware;

use App\Models\Config;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigLoader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $configurations = Config::all();
            
            foreach ($configurations as $config) {
                config(['amir.' . $config->key => $config->value]);
            }
        } catch (\Exception $exception) {
            //
        }
        return $next($request);
    }
}
