<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConfigTitle;
use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    /**
     * GET /api/companies/{company}/config
     *
     * Query (optional):
     * - category
     * - key          (یک کلید خاص، مثلاً bank)
     * - include_global (bool, default true) تنظیمات سراسری با company_id = null
     * - only_mapped  (bool) فقط کلیدهای ConfigTitle (نگاشت حساب‌ها)
     * - as_map       (bool) خروجی به صورت key => value برای مصرف ساده‌تر ایجنت
     * - page, per_page
     */
    public function index(Request $request, int $company): JsonResponse
    {
        // بعد از middleware api-company، FiscalYearScope روی سال فعال است
        $companyConfigs = Config::query()->orderBy('key');

        if ($request->filled('category')) {
            $companyConfigs->where('category', $request->input('category'));
        }

        if ($request->filled('key')) {
            $companyConfigs->where('key', strtolower($request->input('key')));
        }

        if ($request->boolean('only_mapped')) {
            $mappedKeys = array_map(
                fn (ConfigTitle $t) => strtolower($t->value),
                ConfigTitle::cases()
            );
            $companyConfigs->whereIn('key', $mappedKeys);
        }

        $items = $companyConfigs->get();

        // اختیاری: merge تنظیمات global
        if ($request->boolean('include_global', true)) {
            $globals = Config::withoutGlobalScope(FiscalYearScope::class)
                ->whereNull('company_id')
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
                ->when($request->filled('key'), fn ($q) => $q->where('key', strtolower($request->input('key'))))
                ->when($request->boolean('only_mapped'), function ($q) {
                    $mappedKeys = array_map(
                        fn (ConfigTitle $t) => strtolower($t->value),
                        ConfigTitle::cases()
                    );

                    return $q->whereIn('key', $mappedKeys);
                })
                ->orderBy('key')
                ->get();

            // company override روی global با همان key
            $companyKeys = $items->pluck('key')->all();
            $globals = $globals->reject(fn (Config $c) => in_array($c->key, $companyKeys, true));
            $items = $items->concat($globals)->sortBy('key')->values();
        } else {
            $items = $items->values();
        }

        // resolve subject برای valueهای عددی که id حساب هستند
        $subjectIds = $items
            ->pluck('value')
            ->filter(fn ($v) => is_numeric($v) && (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $subjects = $subjectIds
            ? Subject::query()->whereIn('id', $subjectIds)->get(['id', 'code', 'name', 'parent_id'])->keyBy('id')
            : collect();

        $titleMap = collect(ConfigTitle::cases())->mapWithKeys(
            fn (ConfigTitle $t) => [strtolower($t->value) => $t->label()]
        );

        $mapped = $items->map(function (Config $config) use ($subjects, $titleMap, $company) {
            $value = $config->value;
            $subject = null;

            if (is_numeric($value) && (int) $value > 0 && $subjects->has((int) $value)) {
                $s = $subjects->get((int) $value);
                $subject = [
                    'id' => $s->id,
                    'code' => $s->code,
                    'name' => $s->name,
                    'parent_id' => $s->parent_id,
                ];
            }

            $isGlobal = $config->company_id === null;

            return [
                'id' => $config->id,
                'key' => $config->key,
                'value' => $value,
                'typed_value' => $this->castValue($value, $config->type),
                'desc' => $config->desc ?: ($titleMap[$config->key] ?? null),
                'label' => $titleMap[$config->key] ?? $config->desc,
                'type' => $config->type,
                'category' => $config->category,
                'company_id' => $config->company_id,
                'fiscal_year_id' => $config->fiscal_year_id ?? null,
                'scope' => $isGlobal ? 'global' : 'company',
                'is_account_mapping' => $titleMap->has($config->key),
                'subject' => $subject,
            ];
        })->values();

        // pagination
        $total = $mapped->count();
        $perPage = min(max((int) $request->input('per_page', 100), 1), 500);
        $page = max((int) $request->input('page', 1), 1);
        $lastPage = max((int) ceil($total / $perPage), 1);
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;
        $pageItems = $mapped->slice($offset, $perPage)->values();

        $payload = [
            'items' => $pageItems,
            'filters' => [
                'category' => $request->input('category'),
                'key' => $request->input('key') ? strtolower($request->input('key')) : null,
                'include_global' => $request->boolean('include_global', true),
                'only_mapped' => $request->boolean('only_mapped'),
                'as_map' => $request->boolean('as_map'),
            ],
            'meta' => [
                'company_id' => $company,
                'fiscal_year_id' => getScopedFiscalYear(),
                'count' => $total,
                'account_mapping_keys' => array_map(
                    fn (ConfigTitle $t) => strtolower($t->value),
                    ConfigTitle::cases()
                ),
                'note' => 'Company configs are fiscal-year scoped. Global configs have company_id=null. For account mappings, value is usually a subject (account) id.',
            ],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? null : $offset + 1,
                'to' => $total === 0 ? null : min($offset + $perPage, $total),
            ],
        ];

        // فرم compact برای ایجنت: { "bank": 12, "cash_book": 5, ... }
        if ($request->boolean('as_map')) {
            $payload['map'] = $mapped->mapWithKeys(
                fn (array $row) => [$row['key'] => $row['typed_value']]
            );
        }

        return response()->json(['data' => $payload]);
    }

    private function castValue(mixed $value, mixed $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        // type در FreeAmir اغلب کد داخلی است؛ heuristic امن:
        if (is_numeric($value) && ! str_contains((string) $value, '.')) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $lower = strtolower((string) $value);
        if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return (string) $value;
    }
}
