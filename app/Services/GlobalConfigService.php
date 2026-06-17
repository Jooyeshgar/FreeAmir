<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;

class GlobalConfigService
{
    public const SETTINGS = [
        'app_env' => ['local', 'production'],
        'app_locale' => ['fa', 'en'],
        'app_debug' => ['true', 'false'],
    ];

    public function all(): array
    {
        $stored = Config::withoutGlobalScope(FiscalYearScope::class)->whereNull('company_id')->pluck('value', 'key');

        $values = [];
        foreach (array_keys(self::SETTINGS) as $key) {
            $values[$key] = $stored[$key] ?? null;
        }

        return $values;
    }

    public function update(array $values): void
    {
        foreach (array_keys(self::SETTINGS) as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];
            $value = ($value === null || $value === '' || $value === 'default') ? null : (string) $value;

            Config::withoutGlobalScope(FiscalYearScope::class)->updateOrCreate(
                ['key' => $key, 'company_id' => null],
                ['value' => $value, 'type' => 3, 'category' => 1, 'desc' => __($key)],
            );
        }
    }
}
