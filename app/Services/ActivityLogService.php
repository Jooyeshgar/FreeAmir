<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ActivityLogService
{
    private const HIDDEN_ATTRIBUTE_PATTERN = '/password|passphrase|remember_token|token|secret|private_key|certificate/i';

    private const IGNORED_MODEL_ATTRIBUTES = ['created_at', 'updated_at'];

    private ?User $cachedActor = null;

    private bool $actorCached = false;

    private ?Request $actorRequest = null;

    private int|string|null $actorAuthenticatedUserId = null;

    /** @var array<string, array<string, mixed>> */
    private array $pendingModelEvents = [];

    private array $fiscalYearColumnCache = [];

    private ?Request $activeRequest = null;

    private ?User $requestAuthenticatedUser = null;

    /**
     * Update matching models without bypassing Eloquent model events.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $attributes
     */
    public function updateModels(Builder $query, array $attributes): int
    {
        $updated = 0;

        $query->eachById(function (Model $model) use ($attributes, &$updated): void {
            if ($model->update($attributes)) {
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Delete matching models without bypassing Eloquent model events.
     *
     * @param  Builder<Model>  $query
     */
    public function deleteModels(Builder $query): int
    {
        $deleted = 0;

        $query->eachById(function (Model $model) use (&$deleted): void {
            if ($model->delete()) {
                $deleted++;
            }
        });

        return $deleted;
    }

    public function resolveActor(?User $authenticatedUser = null): ?User
    {
        $authenticatedUser ??= auth()->user();
        $currentRequest = app()->bound('request') ? request() : null;
        $authenticatedUserId = $authenticatedUser?->getAuthIdentifier();

        if ($this->actorCached && $this->actorRequest === $currentRequest && $this->actorAuthenticatedUserId === $authenticatedUserId) {
            return $this->cachedActor;
        }

        $this->cachedActor = $authenticatedUser;
        $this->actorCached = true;
        $this->actorRequest = $currentRequest;
        $this->actorAuthenticatedUserId = $authenticatedUserId;

        return $this->cachedActor;
    }

    public function beginRequest(Request $request): void
    {
        $this->endRequest();
        $this->activeRequest = $request;
        $this->requestAuthenticatedUser = $request->user();
    }

    public function endRequest(): void
    {
        $this->pendingModelEvents = [];
        $this->activeRequest = null;
        $this->requestAuthenticatedUser = null;
    }

    /**
     * Record a successful state-changing HTTP request.
     */
    public function recordRequest(Request $request, ?User $actor = null, ?User $authenticatedUser = null): void
    {
        $authenticatedUser ??= $this->requestAuthenticatedUser ?? $request->user();
        $actor ??= $this->resolveActor($authenticatedUser);

        if (! $actor || ! config('activitylog.enabled')) {
            return;
        }

        $routeName = $request->route()?->getName();
        $method = strtoupper($request->method());
        $models = array_values($this->pendingModelEvents);
        $modelTypes = collect($models)->pluck('model_type')->filter()->unique()->values()->all();
        $modelIds = collect($models)->pluck('model_id')->filter(fn (mixed $id): bool => $id !== null)->unique()->values()->all();
        $modelNumbers = collect($models)->pluck('model_number')->filter(fn (mixed $number): bool => $number !== null && $number !== '')->unique()->values()->all();
        $fiscalYearIds = collect($models)->pluck('fiscal_year_id')->filter()->unique()->values()->all();

        activity('request')->causedBy($actor)->event(strtolower($method))->withProperties([
            'route' => $routeName,
            'method' => $method,
            'path' => '/'.ltrim($request->path(), '/'),
            'fiscal_year_id' => $this->requestFiscalYearId($request),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '…'),
            'request_input' => $this->sanitize($request->all()),
            'models' => $models,
            'model_types' => $modelTypes,
            'model_ids' => $modelIds,
            'model_numbers' => $modelNumbers,
            'fiscal_year_ids' => $fiscalYearIds,
            'request_time' => now()->toAtomString(),
        ])->log(trim($method.' '.($routeName ?: '/'.ltrim($request->path(), '/'))));
    }

    /**
     * Record an application model create, update, or delete event.
     */
    public function recordModelEvent(string $event, Model $model, ?Request $request = null): void
    {
        $authenticatedUser = $this->requestAuthenticatedUser ?? auth()->user();

        if (! config('activitylog.enabled') || ! $authenticatedUser || $model instanceof Activity || ! str_starts_with($model::class, 'App\\Models\\')) {
            return;
        }

        $changes = $this->modelChanges($event, $model);

        if ($event === 'updated' && $changes['attributes'] === []) {
            return;
        }

        $request ??= request();

        if ($request === $this->activeRequest) {
            $this->bufferModelEvent($event, $model, $changes, $request);

            return;
        }

        $actor = $this->resolveActor($authenticatedUser);

        if (! $actor) {
            return;
        }

        activity('model')->causedBy($actor)->performedOn($model)->event($event)->withProperties(array_filter([
            ...$changes,
            'model_label' => $this->modelLabel($model),
            'model_number' => $this->modelNumber($model),
            'fiscal_year_id' => $this->modelFiscalYearId($model, $request),
            'company_id' => $model instanceof Company ? (int) $model->getKey() : null,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
        ], fn (mixed $value) => $value !== null))->log($event);
    }

    /**
     * Build the filtered workspace audit-log payload.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        $visibleFiscalYearIds = $this->visibleFiscalYearIds();
        $visibility = $this->visibleActivityIds($visibleFiscalYearIds, isset($filters['fiscal_year_id']) ? (int) $filters['fiscal_year_id'] : null, null);
        $visible = Activity::query()->whereIntegerInRaw('id', $visibility['all']);
        $query = Activity::query()->whereIntegerInRaw('id', $visibility['filtered']);

        $query->when($filters['search'] ?? null, function ($query, string $search) {
            $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('model_type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        })
            ->when($filters['action'] ?? null, function ($query, string $action) {
                $actions = match ($action) {
                    'created' => ['created', 'post'],
                    'updated' => ['updated', 'put', 'patch'],
                    'deleted' => ['deleted', 'delete'],
                    default => [$action],
                };

                $query->whereIn('action', $actions);
            })
            ->when($filters['user_id'] ?? null, fn ($query, int|string $userId) => $query->where('user_id', $userId))
            ->when($filters['model_type'] ?? null, fn ($query, string $modelType) => $query->where(function ($query) use ($modelType) {
                $query->where('model_type', $modelType)->orWhereJsonContains('details->model_types', $modelType);
            }))
            ->when($filters['model_reference'] ?? null, function ($query, string $reference) {
                $reference = trim($reference);

                $query->where(function ($query) use ($reference) {
                    $query->where('model_id', 'like', "%{$reference}%")
                        ->orWhereRaw('CAST(details AS CHAR) LIKE ?', ["%{$reference}%"]);
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date));

        $activities = $this->paginateGroupedActivities($query);
        $impersonatedUserIds = $activities->getCollection()
            ->map(fn (Activity $activity) => $activity->details?->get('impersonated_user_id'))->filter()->unique();
        $filterFiscalYearIds = empty($filters['fiscal_year_id']) ? $visibleFiscalYearIds : $visibleFiscalYearIds->intersect([(int) $filters['fiscal_year_id']]);

        return [
            'activities' => $activities,
            'metrics' => $this->metrics(clone $visible),
            'users' => User::query()->whereHas('fiscalYears', fn (Builder $query) => $query->whereIn('fiscal_years.id', $filterFiscalYearIds))->orderBy('name')->get(['id', 'name', 'email']),
            'impersonatedUsers' => User::query()->whereIn('id', $impersonatedUserIds)->get(['id', 'name', 'email'])->keyBy('id'),
            'fiscalYears' => FiscalYear::query()->with('company:id,name')->whereIn('id', $visibleFiscalYearIds)->orderByDesc('year')->get(['id', 'company_id', 'year']),
            'modelTypes' => $this->availableModelTypes(clone $visible),
            'filters' => $filters,
        ];
    }

    public function findVisible(int|string $id): Activity
    {
        $visibility = $this->visibleActivityIds($this->visibleFiscalYearIds(), null, $id);

        return Activity::query()->whereIntegerInRaw('id', $visibility['all'])->findOrFail($id);
    }

    /**
     * Resolve ownership from the affected model snapshots.
     * Resolve tenant models from their own ownership; retain recorded context for global events.
     * Every model in an aggregate must be validated before any part of the request is exposed.
     *
     * @param  Collection<int, int|string>  $fiscalYearIds
     * @return array{all: array<int>, filtered: array<int>}
     */
    private function visibleActivityIds(Collection $fiscalYearIds, ?int $filterFiscalYearId, int|string|null $activityId): array
    {
        $allowed = $fiscalYearIds->map(fn ($id): int => (int) $id)->values();
        if ($allowed->isEmpty()) {
            return ['all' => [], 'filtered' => []];
        }

        $companyYears = FiscalYear::query()->whereIn('id', $allowed)->get(['id', 'company_id'])
            ->groupBy('company_id')->map(fn (Collection $years): Collection => $years->pluck('id'));
        $ids = [];
        $filteredIds = [];

        Activity::query()->select(['id', 'source', 'model_type', 'model_id', 'details'])
            ->when($activityId !== null, fn (Builder $query) => $query->whereKey($activityId))->toBase()
            ->chunkById(500, function (Collection $activities) use ($allowed, $companyYears, $filterFiscalYearId, &$ids, &$filteredIds): void {
                $modelsByActivity = $activities->mapWithKeys(function (object $activity): array {
                    $details = $this->detailsCollection($activity->details ?? null);
                    $models = $activity->source === 'model' ? [[
                        'model_type' => $activity->model_type,
                        'model_id' => $activity->model_id,
                        'fiscal_year_id' => $details->get('fiscal_year_id'),
                        'attributes' => $details->get('attributes', []),
                        'old' => $details->get('old', []),
                    ]] : $details->get('models', []);
                    if ($models === []) {
                        $models = [[
                            'fiscal_year_id' => $details->get('fiscal_year_id'),
                            'fiscal_year_ids' => $details->get('fiscal_year_ids', []),
                        ]];
                    }

                    return [$activity->id => collect($models)];
                });
                $ownership = $this->loadModelOwnership($modelsByActivity->flatten(1));
                $belongs = fn (array $model, Collection $years): bool => $this->modelBelongsToFiscalYears($model, $years, $ownership, $companyYears);

                foreach ($modelsByActivity as $id => $models) {
                    // Keep the whole request together; every affected model must be accessible.
                    if ($models->isEmpty() || ! $models->every(fn (mixed $model): bool => is_array($model) && $belongs($model, $allowed))) {
                        continue;
                    }

                    $ids[] = (int) $id;
                    if ($filterFiscalYearId === null || ($allowed->contains($filterFiscalYearId)
                        && $models->contains(fn (array $model): bool => $this->modelMatchesFiscalYear($model, $filterFiscalYearId, $ownership, $companyYears)))) {
                        $filteredIds[] = (int) $id;
                    }
                }
            });

        return ['all' => $ids, 'filtered' => $filteredIds];
    }

    private function detailsCollection(mixed $details): Collection
    {
        if ($details instanceof Collection) {
            return $details;
        }

        if (is_string($details)) {
            $details = json_decode($details, true);
        }

        return collect(is_array($details) ? $details : []);
    }

    /**
     * Load only current ownership, once per model type in each chunk.
     * Missing records remain distinguishable from records with null ownership.
     *
     * @return array<class-string<Model>, Collection>
     */
    private function loadModelOwnership(Collection $models): array
    {
        $ownership = [];
        $validModels = $models->filter(function (mixed $model): bool {
            $type = is_array($model) ? ($model['model_type'] ?? null) : null;

            return is_string($type) && str_starts_with($type, 'App\\Models\\') && class_exists($type) && is_subclass_of($type, Model::class) && isset($model['model_id']);
        });

        foreach ($validModels->groupBy('model_type') as $type => $records) {
            if (in_array($type, [FiscalYear::class, Company::class], true)) {
                continue;
            }

            $instance = new $type;
            $table = $instance->getTable();
            $hasFiscalYear = $this->fiscalYearColumnCache[$table] ??= $instance->getConnection()->getSchemaBuilder()->hasColumn($table, 'fiscal_year_id');
            if ($hasFiscalYear) {
                $ownership[$type] = $type::withoutGlobalScopes()->whereKey($records->pluck('model_id')->unique()->all())
                    ->pluck('fiscal_year_id', $instance->getKeyName());
            }
        }

        return $ownership;
    }

    /**
     * Unknown ownership fails closed. Deleted models may use their recorded fiscal-year snapshot.
     */
    private function modelBelongsToFiscalYears(array $model, Collection $allowedFiscalYearIds, array $ownership, Collection $companyYears): bool
    {
        $type = $model['model_type'] ?? null;
        $id = $model['model_id'] ?? null;

        if ($type === null) {
            $years = collect([$model['fiscal_year_id'] ?? null])->merge($model['fiscal_year_ids'] ?? [])->filter()->map(fn ($year): int => (int) $year);

            return $years->isNotEmpty() && $years->every(fn (int $year): bool => $allowedFiscalYearIds->contains($year));
        }

        if (! is_string($type) || $id === null) {
            return false;
        }

        if ($type === FiscalYear::class) {
            return $allowedFiscalYearIds->contains((int) $id);
        }

        if ($type === Company::class) {
            return $companyYears->get($id, collect())->intersect($allowedFiscalYearIds)->isNotEmpty();
        }

        if (! isset($ownership[$type])) {
            // Global model events still belong to their recorded workspace context.
            return $allowedFiscalYearIds->contains((int) ($model['fiscal_year_id'] ?? 0));
        }

        $exists = $ownership[$type]->has($id);
        $snapshotFiscalYearIds = collect([
            data_get($model, 'attributes.fiscal_year_id'),
            data_get($model, 'old.fiscal_year_id'),
            $exists ? $ownership[$type]->get($id) : data_get($model, 'fiscal_year_id'),
        ])->filter()->map(fn ($id): int => (int) $id);

        // A current record with null ownership must never fall back to legacy request context.
        if ($exists && ! $ownership[$type]->get($id)) {
            return false;
        }

        return $snapshotFiscalYearIds->isNotEmpty() && $snapshotFiscalYearIds->every(fn (int $id): bool => $allowedFiscalYearIds->contains($id));
    }

    private function modelMatchesFiscalYear(array $model, int $fiscalYearId, array $ownership, Collection $companyYears): bool
    {
        $type = $model['model_type'] ?? null;
        $id = $model['model_id'] ?? null;

        if ($type === Company::class) {
            return $companyYears->get($id, collect())->contains($fiscalYearId);
        }

        if ($type === FiscalYear::class) {
            return (int) $id === $fiscalYearId;
        }

        $years = collect([
            data_get($model, 'attributes.fiscal_year_id'),
            data_get($model, 'old.fiscal_year_id'),
            isset($ownership[$type ?? '']) && $ownership[$type]->has($id)
                ? $ownership[$type]->get($id) : ($model['fiscal_year_id'] ?? null),
        ])->merge($model['fiscal_year_ids'] ?? []);

        return $years->filter()->map(fn ($year): int => (int) $year)->contains($fiscalYearId);
    }

    private function visibleFiscalYearIds(): Collection
    {
        return auth()->user()?->fiscalYears()->pluck('fiscal_years.id') ?? collect();
    }

    private function paginateGroupedActivities(Builder $query): LengthAwarePaginator
    {
        $perPage = 25;
        $page = Paginator::resolveCurrentPage();
        $firstItem = ($page - 1) * $perPage;
        $lastItem = $firstItem + $perPage;
        $selectedIds = [];
        $groupCount = 0;
        $pendingRequest = null;
        $pendingModels = collect();

        $addGroup = function (Collection $group) use (&$groupCount, $firstItem, $lastItem, &$selectedIds): void {
            if ($groupCount >= $firstItem && $groupCount < $lastItem) {
                $group->each(function (object $activity) use (&$selectedIds): void {
                    $selectedIds[] = $activity->id;
                });
            }

            $groupCount++;
        };

        $flushRequest = function () use (&$pendingRequest, &$pendingModels, $addGroup): void {
            if (! $pendingRequest) {
                return;
            }

            $pendingModels = $pendingModels->sortByDesc(fn (object $activity): int => $activity->created_at?->getTimestamp() ?? 0)->values();

            $relatedIndexes = $pendingModels->keys()
                ->filter(fn (int $index): bool => $this->belongsToRequest($pendingRequest, $pendingModels[$index]));

            if ($relatedIndexes->isEmpty()) {
                $addGroup(collect([$pendingRequest]));
                $pendingModels->each(fn (object $activity) => $addGroup(collect([$activity])));
            } else {
                $firstRelatedIndex = $relatedIndexes->first();
                $relatedActivities = $relatedIndexes->map(fn (int $index): object => $pendingModels[$index]);
                $requestActivity = $pendingRequest;

                $pendingModels->each(function (object $activity, int $index) use ($addGroup, $firstRelatedIndex, $relatedIndexes, $relatedActivities, $requestActivity): void {
                    if ($index === $firstRelatedIndex) {
                        $addGroup(collect([$requestActivity])->concat($relatedActivities));
                    }

                    if (! $relatedIndexes->contains($index)) {
                        $addGroup(collect([$activity]));
                    }
                });
            }

            $pendingRequest = null;
            $pendingModels = collect();
        };

        // Group legacy request/model pairs using only their matching metadata.
        $listingQuery = (clone $query)->select(['id', 'source', 'user_id', 'created_at'])
            ->selectRaw("JSON_OBJECT('route', JSON_EXTRACT(details, '$.route')) AS details")->toBase();

        foreach ($listingQuery->lazyByIdDesc(500) as $activity) {
            $activity->details = $this->detailsCollection($activity->details);
            $activity->created_at = $activity->created_at ? Carbon::parse($activity->created_at) : null;
            if ($activity->source === 'request') {
                $flushRequest();
                $pendingRequest = $activity;

                continue;
            }

            if ($pendingRequest) {
                $pendingModels->push($activity);
            } else {
                $addGroup(collect([$activity]));
            }
        }

        $flushRequest();
        $selectedById = (clone $query)->whereIn('id', $selectedIds)
            ->selectRaw("activity_log.*, CASE WHEN source = 'request' THEN JSON_REMOVE(details, '$.models') ELSE details END AS details")
            ->with('user:id,name,email')->get()->keyBy('id');
        $selectedActivities = new EloquentCollection(collect($selectedIds)->map(fn ($id) => $selectedById->get($id))->filter()->all());

        return (new LengthAwarePaginator(
            $selectedActivities,
            $groupCount,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        ))->withQueryString();
    }

    private function belongsToRequest(object $requestActivity, object $modelActivity): bool
    {
        $requestRoute = $requestActivity->details?->get('route');

        return filled($requestRoute)
            && $modelActivity->user_id === $requestActivity->user_id
            && $modelActivity->details?->get('route') === $requestRoute
            && abs(($modelActivity->created_at?->getTimestamp() ?? 0) - ($requestActivity->created_at?->getTimestamp() ?? 0)) <= 10;
    }

    /**
     * @return array{total: int, today: int, model: int, request: int}
     */
    private function metrics(Builder $activities): array
    {
        return [
            'total' => (clone $activities)->count(),
            'today' => (clone $activities)->whereDate('created_at', Carbon::today())->count(),
            'model' => (clone $activities)->where('source', 'model')->count()
                + (clone $activities)->where('source', 'request')->whereJsonLength('details->models', '>', 0)->count(),
            'request' => (clone $activities)->where('source', 'request')->count(),
        ];
    }

    private function availableModelTypes(Builder $visible): Collection
    {
        $directTypes = (clone $visible)->whereNotNull('model_type')->distinct()->pluck('model_type');
        $aggregatedTypes = (clone $visible)->where('source', 'request')->whereNotNull('details')->toBase()
            ->selectRaw("JSON_EXTRACT(details, '$.model_types') AS model_types")->pluck('model_types')
            ->flatMap(fn (mixed $types): array => (array) (is_string($types) ? json_decode($types, true) : $types));

        return $directTypes->merge($aggregatedTypes)->filter()->unique()->sort()->values();
    }

    /**
     * @return array{attributes: array<string, mixed>, old?: array<string, mixed>}
     */
    private function modelChanges(string $event, Model $model): array
    {
        if ($event === 'created') {
            return ['attributes' => $this->sanitizeModelAttributes($model->getAttributes())];
        }

        if ($event === 'deleted') {
            return ['old' => $this->sanitizeModelAttributes($model->getAttributes())];
        }

        // `getChanges()` can contain an attribute that was assigned during a
        // request even though it still represents the same persisted value
        // (the common example is assigning null to an already-null column).
        // Build the diff from the model's original values and compare the
        // sanitized values as a final guard, so those assignments never reach
        // the audit log.
        $attributes = collect($model->getChanges())
            ->filter(fn (mixed $value, string $key): bool => ! $model->originalIsEquivalent($key))
            ->map(fn (mixed $value) => $this->sanitizeValue($value))
            ->all();
        $old = [];

        foreach (array_keys($attributes) as $key) {
            $oldValue = $this->sanitizeValue($model->getRawOriginal($key));

            if ($this->valuesEquivalent($oldValue, $attributes[$key])) {
                unset($attributes[$key]);

                continue;
            }

            $old[$key] = $oldValue;
        }

        return ['attributes' => $this->sanitizeModelAttributes($attributes), 'old' => $this->sanitizeModelAttributes($old)];
    }

    private function valuesEquivalent(mixed $old, mixed $new): bool
    {
        if ($old === $new) {
            return true;
        }

        return is_scalar($old) && is_scalar($new) && (string) $old === (string) $new;
    }

    /**
     * @param  array{attributes: array<string, mixed>, old?: array<string, mixed>}  $changes
     */
    private function bufferModelEvent(string $event, Model $model, array $changes, Request $request): void
    {
        $key = $model::class.'#'.$model->getKey();
        $incoming = [
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'event' => $event,
            'model_label' => $this->modelLabel($model),
            'model_number' => $this->modelNumber($model),
            'fiscal_year_id' => $this->modelFiscalYearId($model, $request),
            'company_id' => $model instanceof Company ? (int) $model->getKey() : null,
            'old' => $changes['old'] ?? [],
            'attributes' => $changes['attributes'] ?? [],
        ];

        if (! isset($this->pendingModelEvents[$key])) {
            $this->pendingModelEvents[$key] = $incoming;

            return;
        }

        $existing = $this->pendingModelEvents[$key];

        if ($existing['event'] === 'created' && $event === 'deleted') {
            unset($this->pendingModelEvents[$key]);

            return;
        }

        $old = $existing['old'];
        if ($existing['event'] !== 'created') {
            foreach ($incoming['old'] as $attribute => $value) {
                if (! array_key_exists($attribute, $old)) {
                    $old[$attribute] = $value;
                }
            }
        }

        $attributes = $existing['attributes'];
        foreach ($incoming['attributes'] as $attribute => $value) {
            $attributes[$attribute] = $value;
        }

        if ($existing['event'] === 'created') {
            $old = [];
        } elseif ($event === 'deleted') {
            $old = $existing['event'] === 'created' ? [] : [...$incoming['old'], ...$old];
            $attributes = [];
        } elseif ($existing['event'] !== 'created') {
            foreach (array_keys($attributes) as $attribute) {
                if (array_key_exists($attribute, $old) && $old[$attribute] === $attributes[$attribute]) {
                    unset($old[$attribute], $attributes[$attribute]);
                }
            }
        }

        $this->pendingModelEvents[$key] = [
            ...$incoming,
            'event' => $existing['event'] === 'created' ? 'created' : $event,
            'old' => $old,
            'attributes' => $attributes,
        ];

        if ($this->pendingModelEvents[$key]['event'] === 'updated' && $attributes === []) {
            unset($this->pendingModelEvents[$key]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function sanitizeModelAttributes(array $attributes): array
    {
        return collect($attributes)->except(self::IGNORED_MODEL_ATTRIBUTES)
            ->reject(fn (mixed $value, string $key) => preg_match(self::HIDDEN_ATTRIBUTE_PATTERN, $key) === 1)
            ->map(fn (mixed $value) => $this->sanitizeValue($value))
            ->all();
    }

    private function modelLabel(Model $model): string
    {
        foreach (['name', 'title', 'number', 'code', 'email'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_scalar($value) && (string) $value !== '') {
                return Str::limit((string) $value, 150, '…');
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    private function modelNumber(Model $model): int|float|string|null
    {
        $number = $model->getAttribute('number');

        return is_scalar($number) && (string) $number !== '' ? $number : null;
    }

    private function modelFiscalYearId(Model $model, Request $request): ?int
    {
        if ($model instanceof FiscalYear) {
            return (int) $model->getKey();
        }

        if (array_key_exists('fiscal_year_id', $model->getAttributes())) {
            $fiscalYearId = $model->getAttribute('fiscal_year_id');

            return $fiscalYearId ? (int) $fiscalYearId : null;
        }

        return $model instanceof Company ? null : $this->requestFiscalYearId($request);
    }

    private function requestFiscalYearId(Request $request): ?int
    {
        if ($request->hasSession() && $request->session()->get('interface_mode') === 'management') {
            return null;
        }

        $fiscalYearId = config('active-fiscal-year-id') ?? $request->cookie('active-fiscal-year-id');

        return $fiscalYearId ? (int) $fiscalYearId : null;
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match(self::HIDDEN_ATTRIBUTE_PATTERN, $key) === 1) {
            return '[REDACTED]';
        }

        if ($value instanceof UploadedFile) {
            return ['file' => $value->getClientOriginalName(), 'mime_type' => $value->getClientMimeType()];
        }

        if (is_array($value)) {
            return collect($value)->take(50)->map(fn (mixed $item, int|string $itemKey) => $this->sanitize($item, (string) $itemKey))->all();
        }

        return $this->sanitizeValue($value);
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return Str::limit($value, 500, '…');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: (string) $value, 500, '…');
    }
}
