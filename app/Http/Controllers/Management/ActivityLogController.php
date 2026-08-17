<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Company;
use App\Services\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityLogController extends Controller
{
    private array $numberColumnByModel = [];

    private array $showRouteByModel = [];

    public function __construct(private readonly ActivityLogService $activityLogService) {}

    /** Render the activity log with validated filters and localized dates. */
    public function index(Request $request): View
    {
        $request->session()->put('interface_mode', 'management');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'user_id' => ['nullable', 'integer'],
            'model_type' => ['nullable', 'string', 'max:255'],
            'model_identifier' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'string', 'max:10'],
            'date_to' => ['nullable', 'string', 'max:10'],
        ]);

        foreach (['date_from', 'date_to'] as $dateFilter) {
            if (filled($filters[$dateFilter] ?? null)) {
                $filters[$dateFilter] = jalaliInputToGregorian($filters[$dateFilter], $dateFilter);
            }
        }

        if (isset($filters['date_from'], $filters['date_to']) && $filters['date_from'] > $filters['date_to']) {
            throw ValidationException::withMessages([
                'date_to' => [__('validation.after_or_equal', ['attribute' => __('To date'), 'date' => __('From date')])],
            ]);
        }

        return view('super-admin.activity-logs.index', $this->viewData(
            $this->activityLogService->index($filters),
            $request,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function viewData(array $data, Request $request): array
    {
        $companyLookup = $data['companies']->keyBy('id');
        $impersonatedUsers = $data['impersonatedUsers'];
        $recordingEnabled = (bool) config('activitylog.enabled');
        $activeFilterCount = collect([
            $data['filters']['search'] ?? null,
            $data['filters']['action'] ?? null,
            $data['filters']['user_id'] ?? null,
            $data['filters']['company_id'] ?? null,
            $data['filters']['model_type'] ?? null,
            $data['filters']['model_identifier'] ?? null,
            $request->input('date_from'),
            $request->input('date_to'),
        ])->filter(fn (mixed $value): bool => filled($value))->count();

        $rows = $data['activities']->getCollection()->map(fn (Activity $activity): array => $this->activityRow(
            $activity,
            $companyLookup,
            $impersonatedUsers,
        ));
        $data['activities']->setCollection($this->mergeRequestRows($rows));

        return [
            ...$data,
            'activeFilterCount' => $activeFilterCount,
            'activeFilterCountLabel' => localizeNumber($activeFilterCount),
            'actionOptions' => $this->actionOptions(),
            'companyOptions' => $data['companies']->map(fn (Company $company): array => [
                'value' => $company->id,
                'label' => $company->name.' - '.localizeNumber($company->fiscal_year),
            ]),
            'dateFromValue' => $request->input('date_from', ''),
            'dateToValue' => $request->input('date_to', ''),
            'metricCards' => $this->metricCards($data['metrics']),
            'modelOptions' => $data['modelTypes']->map(fn (string $modelType): array => [
                'value' => $modelType,
                'label' => __(class_basename($modelType)),
            ]),
            'recordingEnabled' => $recordingEnabled,
            'recordingStatusLabel' => $recordingEnabled ? __('Recording enabled') : __('Recording disabled'),
        ];
    }

    private function actionOptions(): array
    {
        return [
            'created' => [
                'label' => __('Created'),
                'style' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300',
            ],
            'updated' => [
                'label' => __('Updated'),
                'style' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-300',
            ],
            'deleted' => [
                'label' => __('Deleted'),
                'style' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300',
            ],
        ];
    }

    private function metricCards(array $metrics): array
    {
        return [
            [
                'label' => __('All activity'),
                'value' => localizeNumber(number_format($metrics['total'])),
                'accent' => 'bg-slate-900 dark:bg-slate-100',
                'icon' => 'M5.25 7.5h13.5m-13.5 4.5h13.5m-13.5 4.5h8.25',
            ],
            [
                'label' => __('Today'),
                'value' => localizeNumber(number_format($metrics['today'])),
                'accent' => 'bg-emerald-500',
                'icon' => 'M6.75 3v2.25M17.25 3v2.25M3.75 9.75h16.5m-15 10.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-12a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v12a1.5 1.5 0 0 0 1.5 1.5Z',
            ],
            [
                'label' => __('Model changes'),
                'value' => localizeNumber(number_format($metrics['model'])),
                'accent' => 'bg-sky-500',
                'icon' => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25M21 7.5v9L12 21.75m0-9L3 7.5m9 5.25v9M3 7.5v9l9 5.25',
            ],
            [
                'label' => __('User actions'),
                'value' => localizeNumber(number_format($metrics['request'])),
                'accent' => 'bg-violet-500',
                'icon' => 'M15.59 14.37a6 6 0 0 1-7.18 0M12 6.75h.008v.008H12V6.75Zm3.75 2.25h.008v.008h-.008V9Zm-7.5 0h.008v.008H8.25V9ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            ],
        ];
    }

    /** Convert a stored activity record into the view model used by Blade. */
    private function activityRow(Activity $activity, Collection $companyLookup, Collection $impersonatedUsers): array
    {
        $details = $activity->details ?? collect();
        $requestModels = collect($details->get('models', []));
        $attributes = collect($details->get('attributes', []));
        $old = collect($details->get('old', []));
        $changeKeys = $attributes->keys()->merge($old->keys())->unique();
        $company = $companyLookup->get($details->get('company_id'));
        $impersonatedUser = $impersonatedUsers->get($details->get('impersonated_user_id'));
        $isRequest = $activity->source === 'request';
        $canonicalAction = match ($activity->action) {
            'post' => 'created',
            'put', 'patch' => 'updated',
            'delete' => 'deleted',
            default => $activity->action,
        };
        $action = $this->actionOptions()[$canonicalAction] ?? [
            'label' => __(ucfirst($activity->action ?? 'activity')),
            'style' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300',
        ];
        $modelTitle = __(class_basename($activity->model_type ?? 'Activity'));
        $hasNumberColumn = $this->modelHasNumberColumn($activity->model_type);
        $modelNumber = $hasNumberColumn ? $this->modelNumber($details) : null;
        $modelContextLabel = $modelTitle.($hasNumberColumn ? (filled($modelNumber) ? ' #'.$modelNumber : '') : ' #'.$activity->model_id);
        $modelUrl = $isRequest ? null : $this->modelUrl($activity->model_type, $activity->model_id);
        $route = $details->get('route');
        $routeUrl = $isRequest ? $this->namedRouteUrl($route) : null;
        $modelRows = $isRequest
            ? $requestModels->map(function (array $model): array {
                $type = $model['model_type'] ?? null;
                $id = $model['model_id'] ?? null;
                $hasNumberColumn = $this->modelHasNumberColumn($type);
                $number = $model['model_number'] ?? null;
                $label = __(class_basename($type ?: 'Activity')).($hasNumberColumn && filled($number) ? ' #'.$number : ' #'.$id);

                return [
                    'label' => $label,
                    'url' => $this->modelUrl($type, $id),
                    'changes' => collect($model['new_data'] ?? [])->keys()->merge(collect($model['old_data'] ?? [])->keys())->unique()->map(fn (string $key): array => [
                        'model' => $label,
                        'url' => $this->modelUrl($type, $id),
                        'field' => $key,
                        'old' => $this->formatValue(collect($model['old_data'] ?? [])->get($key)),
                        'new' => $this->formatValue(collect($model['new_data'] ?? [])->get($key)),
                    ]),
                ];
            })
            : collect();
        $allModelLinks = $isRequest ? $modelRows->map(fn (array $model): array => ['label' => $model['label'], 'url' => $model['url']])->unique(fn (array $link): string => $link['label'])->values() : collect();
        $allModelLabels = $allModelLinks->pluck('label')->values();
        $requestChanges = $modelRows->flatMap(fn (array $model): Collection => $model['changes'])->values();

        return [
            'id' => $activity->id,
            'userId' => $activity->user_id,
            'isRequest' => $isRequest,
            'actionLabel' => $isRequest && $requestModels->isNotEmpty()
                ? ($this->actionOptions()[$requestModels->first()['event'] ?? $canonicalAction]['label'] ?? $action['label'])
                : $action['label'],
            'actionStyle' => $action['style'],
            'userName' => $activity->user?->name ?? __('System'),
            'userUrl' => $activity->user ? route('users.show', $activity->user) : null,
            'companyLabel' => $company ? $company->name.' - '.localizeNumber($company->fiscal_year) : null,
            'requestMethod' => $isRequest
                ? strtoupper((string) ($details->get('method') ?: $activity->action))
                : null,
            'route' => $route,
            'contextLabel' => $route ?: ($isRequest ? ($allModelLabels->first() ?: $activity->description) : $modelContextLabel),
            'contextUrl' => $isRequest ? null : $modelUrl,
            'modelContextLabel' => $isRequest ? null : $modelContextLabel,
            'modelContextLabels' => $isRequest ? $allModelLabels : collect([$modelContextLabel]),
            'modelContextLinks' => $isRequest ? $allModelLinks : collect([['label' => $modelContextLabel, 'url' => $modelUrl]]),
            'modelId' => $isRequest ? ($requestModels->first()['model_id'] ?? null) : $activity->model_id,
            'impersonatedUserName' => $impersonatedUser?->name,
            'createdAt' => $activity->created_at?->copy()->setTimezone(config('app.timezone'))->toAtomString(),
            'createdAtTimestamp' => $activity->created_at?->getTimestamp(),
            'createdAtLabel' => formatDateTime($activity->created_at?->copy()->setTimezone(config('app.timezone'))),
            'hasDetails' => $isRequest || $changeKeys->isNotEmpty(),
            'requestContext' => $isRequest ? [
                ['label' => __('Route'), 'value' => $details->get('route') ?: '—', 'url' => $routeUrl],
                ['label' => __('Method'), 'value' => $details->get('method') ?: '—'],
                ['label' => __('Path'), 'value' => $details->get('path') ?: '—'],
                ['label' => __('IP address'), 'value' => $details->get('ip_address') ?: '—'],
                ['label' => __('User agent'), 'value' => $details->get('user_agent') ?: '—'],
            ] : [],
            'requestInput' => filled($details->get('request_input'))
                ? (json_encode($details->get('request_input'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '—')
                : null,
            'changes' => ($isRequest ? $requestChanges : $changeKeys->map(fn (string $key): array => [
                'model' => $modelContextLabel,
                'url' => $modelUrl,
                'field' => $key,
                'old' => $this->formatValue($old->get($key)),
                'new' => $this->formatValue($attributes->get($key)),
            ]))->values(),
        ];
    }

    private function modelHasNumberColumn(?string $modelType): bool
    {
        if (! $modelType || ! class_exists($modelType)) {
            return false;
        }

        return $this->numberColumnByModel[$modelType] ??= Schema::hasColumn((new $modelType)->getTable(), 'number');
    }

    private function modelNumber(Collection $details): int|float|string|null
    {
        $number = $details->get('model_number') ?? collect($details->get('attributes', []))->get('number') ?? collect($details->get('old', []))->get('number');

        return is_scalar($number) && (string) $number !== '' ? $number : null;
    }

    private function modelUrl(?string $modelType, int|string|null $modelId): ?string
    {
        if (! $modelType || $modelId === null || ! class_exists($modelType)) {
            return null;
        }

        $routeName = $this->showRouteByModel[$modelType] ??= $this->findModelShowRoute($modelType);

        return $routeName ? route($routeName, $modelId) : null;
    }

    private function namedRouteUrl(?string $routeName): ?string
    {
        if (blank($routeName) || ! Route::has($routeName)) {
            return null;
        }

        try {
            return route($routeName);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    private function findModelShowRoute(string $modelType): ?string
    {
        $resourceSuffix = Str::plural(Str::kebab(class_basename($modelType))).'.show';

        foreach (Route::getRoutes() as $route) {
            if ($route->getActionMethod() !== 'show' || ! $route->getName() || ! Str::endsWith($route->getName(), $resourceSuffix) || count($route->parameterNames()) !== 1) {
                continue;
            }

            $controllerClass = $route->getControllerClass();

            if (! $controllerClass || ! method_exists($controllerClass, 'show')) {
                continue;
            }

            foreach ((new \ReflectionMethod($controllerClass, 'show'))->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin() && $type->getName() === $modelType) {
                    return $route->getName();
                }
            }
        }

        return null;
    }

    /** Merge legacy model rows with their request row for pre-refactor history. */
    private function mergeRequestRows(Collection $rows): Collection
    {
        $rows = $rows->values();
        $mergedIndexes = [];

        foreach ($rows as $requestIndex => $requestRow) {
            if (! $requestRow['isRequest'] || blank($requestRow['route'])) {
                continue;
            }

            $modelIndexes = $this->relatedModelIndexes($rows, $requestIndex, $requestRow, $mergedIndexes);

            if ($modelIndexes->isEmpty()) {
                continue;
            }

            $modelIndex = $modelIndexes->first();
            $modelRows = $modelIndexes->map(fn (int $index): array => $rows[$index]);
            $modelRow = $rows[$modelIndex];
            $modelRow['requestMethod'] = $requestRow['requestMethod'];
            $modelRow['requestContext'] = $requestRow['requestContext'];
            $modelRow['requestInput'] = $requestRow['requestInput'];
            $modelRow['modelContextLabels'] = $modelRows->flatMap(fn (array $row): Collection => $row['modelContextLabels'])->unique()->values();
            $modelRow['modelContextLinks'] = $modelRows->flatMap(fn (array $row): Collection => $row['modelContextLinks'])->unique(fn (array $link): string => $link['label'])->values();
            $modelRow['changes'] = $modelRows->sortByDesc(fn (array $row): int => $row['createdAtTimestamp'] ?? 0)->flatMap(fn (array $row): Collection => $row['changes'])->values();
            $modelRow['companyLabel'] ??= $requestRow['companyLabel'];
            $modelRow['impersonatedUserName'] ??= $requestRow['impersonatedUserName'];
            $modelRow['hasDetails'] = true;
            $rows[$modelIndex] = $modelRow;
            $mergedIndexes[$requestIndex] = true;

            $modelIndexes->skip(1)->each(function (int $index) use (&$mergedIndexes): void {
                $mergedIndexes[$index] = true;
            });
        }

        return $rows->reject(fn (array $row, int $index): bool => isset($mergedIndexes[$index]))->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $requestRow
     * @param  array<int, bool>  $mergedIndexes
     * @return Collection<int, int>
     */
    private function relatedModelIndexes(Collection $rows, int $requestIndex, array $requestRow, array $mergedIndexes): Collection
    {
        $indexes = collect();

        for ($index = $requestIndex + 1; $index < $rows->count(); $index++) {
            $row = $rows[$index];

            if ($row['isRequest']) {
                break;
            }

            if (! isset($mergedIndexes[$index])
                && $row['userId'] === $requestRow['userId']
                && $row['route'] === $requestRow['route']
                && abs(($row['createdAtTimestamp'] ?? 0) - ($requestRow['createdAtTimestamp'] ?? 0)) <= 10) {
                $indexes->push($index);
            }
        }

        return $indexes;
    }

    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '—';
        }

        return (string) $value;
    }
}
