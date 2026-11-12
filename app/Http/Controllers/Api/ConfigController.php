<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConfigTitle;
use App\Http\Controllers\Controller;
use App\Models\Config;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index(Request $request, int $fiscalYear): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'key' => ['nullable', 'string', 'max:100'],
            'only_mapped' => ['sometimes', 'boolean'],
            'as_map' => ['sometimes', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $mappedKeys = collect(ConfigTitle::cases())
            ->map(fn (ConfigTitle $title) => strtolower($title->value));

        $query = Config::query()->orderBy('key')
            ->when(isset($validated['category']), fn ($q) => $q->where('category', $validated['category']))
            ->when(isset($validated['key']), fn ($q) => $q->where('key', strtolower($validated['key'])))
            ->when($request->boolean('only_mapped'), fn ($q) => $q->whereIn('key', $mappedKeys));

        $configs = $query->paginate($validated['per_page'] ?? 100, ['*'], 'page', $validated['page'] ?? 1);

        $subjectIds = $configs->getCollection()->pluck('value')
            ->filter(fn ($value) => is_numeric($value) && (int) $value > 0)
            ->map(fn ($value) => (int) $value)->unique();

        $subjects = Subject::query()->whereIn('id', $subjectIds)
            ->get(['id', 'code', 'name', 'parent_id'])->keyBy('id');

        $labels = collect(ConfigTitle::cases())->mapWithKeys(
            fn (ConfigTitle $title) => [strtolower($title->value) => $title->label()]
        );

        $items = $configs->getCollection()->map(function (Config $config) use ($subjects, $labels) {
            $subject = is_numeric($config->value) ? $subjects->get((int) $config->value) : null;

            return [
                'id' => $config->id,
                'key' => $config->key,
                'value' => $config->value,
                'description' => $config->desc ?: ($labels[$config->key] ?? null),
                'type' => $config->type,
                'category' => $config->category,
                'fiscal_year_id' => $config->fiscal_year_id,
                'subject' => $subject ? [
                    'id' => $subject->id,
                    'code' => $subject->code,
                    'name' => $subject->name,
                    'parent_id' => $subject->parent_id,
                ] : null,
            ];
        })->values();

        $data = [
            'items' => $items,
            'meta' => [
                'fiscal_year_id' => getActiveFiscalYear(),
                'count' => $configs->total(),
                'account_mapping_keys' => $mappedKeys->values(),
            ],
            'pagination' => [
                'current_page' => $configs->currentPage(),
                'per_page' => $configs->perPage(),
                'total' => $configs->total(),
                'last_page' => $configs->lastPage(),
            ],
        ];

        if ($request->boolean('as_map')) {
            $data['map'] = $items->mapWithKeys(fn (array $item) => [$item['key'] => $item['value']]);
        }

        return response()->json(['data' => $data]);
    }
}
