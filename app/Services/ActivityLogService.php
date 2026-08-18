<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Captures one audit record per HTTP request.
 *
 * Model events are buffered while a request is running and are persisted with the request metadata after the response succeeds.
 * Model events executed outside an HTTP request remain compatible with the legacy model log format.
 */
class ActivityLogService
{
    private const HIDDEN_ATTRIBUTE_PATTERN = '/password|passphrase|remember_token|token|secret|private_key|certificate/i';

    private const IGNORED_MODEL_ATTRIBUTES = ['created_at', 'updated_at'];

    private ?User $cachedActor = null;

    private bool $actorCached = false;

    private ?Request $actorRequest = null;

    private int|string|null $actorAuthenticatedUserId = null;

    private array $pendingModelEvents = [];

    private ?Request $requestBeingProcessed = null;

    private ?User $requestAuthenticatedUser = null;

    public function __construct(private readonly ImpersonateManager $impersonateManager) {}

    public function resolveActor(?User $authenticatedUser = null): ?User
    {
        $authenticatedUser ??= auth()->user();
        $currentRequest = app()->bound('request') ? request() : null;
        $authenticatedUserId = $authenticatedUser?->getAuthIdentifier();

        if ($this->actorCached && $this->actorRequest === $currentRequest && $this->actorAuthenticatedUserId === $authenticatedUserId) {
            return $this->cachedActor;
        }

        $this->cachedActor = $this->impersonateManager->isImpersonating() ? User::query()->find($this->impersonateManager->getImpersonatorId()) : $authenticatedUser;
        $this->actorCached = true;
        $this->actorRequest = $currentRequest;
        $this->actorAuthenticatedUserId = $authenticatedUserId;

        return $this->cachedActor;
    }

    /** Start a fresh request buffer before route/controller execution. */
    public function beginRequest(Request $request): void
    {
        if ($this->requestBeingProcessed !== null) {
            $this->endRequest();
        }

        $this->requestBeingProcessed = $request;
        $this->requestAuthenticatedUser = $request->user();
        $this->pendingModelEvents = [];
    }

    /** Explicitly release all per-request state so the service is safe to reuse. */
    public function endRequest(): void
    {
        $this->pendingModelEvents = [];
        $this->requestBeingProcessed = null;
        $this->requestAuthenticatedUser = null;
        $this->cachedActor = null;
        $this->actorCached = false;
        $this->actorRequest = null;
        $this->actorAuthenticatedUserId = null;
    }

    /** Record a successful HTTP request and its collected model events. */
    public function recordRequest(Request $request, ?User $actor = null, ?User $authenticatedUser = null): void
    {
        $authenticatedUser ??= $this->requestAuthenticatedUser ?? $request->user();
        $actor ??= $this->resolveActor($authenticatedUser);

        if (! $actor || ! config('activitylog.enabled')) {
            return;
        }

        $routeName = $request->route()?->getName();
        $method = strtoupper($request->method());

        $models = collect($this->pendingModelEvents)->values()->all();
        $modelTypes = collect($models)->pluck('model_type')->filter()->unique()->values()->all();
        $modelIds = collect($models)->pluck('model_id')->filter()->unique()->values()->all();
        $modelNumbers = collect($models)->pluck('model_number')->filter(fn (mixed $number): bool => $number !== null && $number !== '')->unique()->values()->all();
        $oldData = collect($models)->mapWithKeys(fn (array $model, int $index): array => [$this->modelKey($model, $index) => $model['old_data'] ?? []])->all();
        $newData = collect($models)->mapWithKeys(fn (array $model, int $index): array => [$this->modelKey($model, $index) => $model['new_data'] ?? []])->all();
        $companyIds = collect($models)->pluck('company_id')->filter()->unique()->values()->all();

        activity('request')->causedBy($actor)->event(strtolower($method))->withProperties([
            'route' => $routeName,
            'method' => $method,
            'path' => '/'.ltrim($request->path(), '/'),
            'company_id' => $this->requestCompanyId($request),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '…'),
            'request_input' => $this->sanitize($request->all()),
            'models' => $models,
            'model_types' => $modelTypes,
            'model_ids' => $modelIds,
            'model_numbers' => $modelNumbers,
            'old_data' => $oldData,
            'new_data' => $newData,
            'company_ids' => $companyIds,
            'request_time' => now()->toAtomString(),
            ...$this->impersonationProperties($actor, $authenticatedUser),
        ])->log(trim($method.' '.($routeName ?: '/'.ltrim($request->path(), '/'))));

        $this->pendingModelEvents = [];
        $this->requestBeingProcessed = null;
        $this->requestAuthenticatedUser = null;
    }

    /**
     * Record an application model create, update, or delete event.
     */
    public function recordModelEvent(string $event, Model $model, ?Request $request = null): void
    {
        $authenticatedUser = auth()->user();
        $actor = $this->resolveActor($authenticatedUser);

        if (! config('activitylog.enabled') || ! $actor || $model instanceof Activity || ! str_starts_with($model::class, 'App\\Models\\')) {
            return;
        }

        if ($event === 'updated' && ! $this->hasRealModelChanges($model)) {
            return;
        }

        $changes = $this->modelChanges($event, $model);
        $request ??= request();

        if ($this->shouldAggregateModelEvent($request)) {
            $this->addOrUpdatePendingModelEvent([
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'event' => $event,
                'model_label' => $this->modelLabel($model),
                'model_number' => $this->modelNumber($model),
                'company_id' => $this->modelCompanyId($model, $request),
                'old_data' => $changes['old'] ?? [],
                'new_data' => $changes['attributes'] ?? [],
            ]);

            return;
        }

        activity('model')->causedBy($actor)->performedOn($model)->event($event)->withProperties(array_filter([
            ...$changes,
            'model_label' => $this->modelLabel($model),
            'model_number' => $this->modelNumber($model),
            'company_id' => $this->modelCompanyId($model, $request),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            ...$this->impersonationProperties($actor, $authenticatedUser),
        ], fn (mixed $value) => $value !== null))->log($event);
    }

    /**
     * Build all data required by the management activity-log page.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function index(array $filters): array
    {
        $query = Activity::query();

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
            ->when($filters['model_type'] ?? null, function ($query, string $modelType) {
                $query->where(function ($query) use ($modelType) {
                    $query->where('model_type', $modelType)
                        ->orWhereJsonContains('details->model_types', $modelType);
                });
            })
            ->when($filters['model_identifier'] ?? null, function ($query, string $identifier) {
                $query->where(function ($query) use ($identifier) {
                    $query->where('model_id', $identifier)
                        ->orWhere('details->model_number', $identifier)
                        ->orWhereJsonContains('details->model_ids', is_numeric($identifier) ? (int) $identifier : $identifier)
                        ->orWhereJsonContains('details->model_numbers', $identifier);

                    if (is_numeric($identifier)) {
                        $query->orWhereJsonContains('details->model_numbers', (int) $identifier);
                    }
                });
            })
            ->when($filters['company_id'] ?? null, function ($query, int|string $companyId) {
                $query->where(function ($query) use ($companyId) {
                    $query->where('details->company_id', (int) $companyId)
                        ->orWhereJsonContains('details->company_ids', (int) $companyId);
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date));

        $activities = $this->paginateGroupedActivities($query);

        $impersonatedUserIds = [];
        foreach ($activities->getCollection() as $activity) {
            $id = $activity->details?->get('impersonated_user_id');
            if ($id !== null) {
                $impersonatedUserIds[] = $id;
            }
        }
        $impersonatedUserIds = array_values(array_unique($impersonatedUserIds));

        return [
            'activities' => $activities,
            'metrics' => $this->metrics(),
            'users' => DB::table('users')->whereIn('id', Activity::query()->whereNotNull('user_id')->select('user_id'))->orderBy('name')->get(['id', 'name', 'email']),
            'impersonatedUsers' => DB::table('users')->whereIn('id', $impersonatedUserIds)->get(['id', 'name', 'email'])->keyBy('id'),
            'companies' => DB::table('companies')->orderByDesc('fiscal_year')->orderBy('name')->get(['id', 'name', 'fiscal_year']),
            'modelTypes' => $this->availableModelTypes(),
            'filters' => $filters,
        ];
    }

    /**
     * Paginate activity rows while keeping legacy request/model pairs together.
     * New request rows already contain their model events and pass through as one row;
     * this grouping only supports records written before the refactor.
     */
    private function paginateGroupedActivities(Builder $query): LengthAwarePaginator
    {
        $perPage = 10;
        $page = Paginator::resolveCurrentPage();
        $firstItem = ($page - 1) * $perPage;
        $lastItem = $firstItem + $perPage;
        $selectedActivities = new EloquentCollection;
        $groupCount = 0;
        $pendingRequest = null;
        $pendingModels = collect();

        $addGroup = function (Collection $group) use (&$groupCount, $firstItem, $lastItem, $selectedActivities): void {
            if ($groupCount >= $firstItem && $groupCount < $lastItem) {
                $group->each(fn (Activity $activity) => $selectedActivities->push($activity));
            }

            $groupCount++;
        };

        $flushRequest = function () use (&$pendingRequest, &$pendingModels, $addGroup): void {
            if (! $pendingRequest) {
                return;
            }

            $pendingModels = $pendingModels->sortByDesc(fn (Activity $activity): int => $activity->created_at?->getTimestamp() ?? 0)->values();

            $relatedIndexes = $pendingModels->keys()
                ->filter(fn (int $index): bool => $this->belongsToRequest($pendingRequest, $pendingModels[$index]));

            if ($relatedIndexes->isEmpty()) {
                $addGroup(collect([$pendingRequest]));
                $pendingModels->each(fn (Activity $activity) => $addGroup(collect([$activity])));
            } else {
                $firstRelatedIndex = $relatedIndexes->first();
                $relatedActivities = $relatedIndexes->map(fn (int $index): Activity => $pendingModels[$index]);
                $requestActivity = $pendingRequest;

                $pendingModels->each(function (Activity $activity, int $index) use ($addGroup, $firstRelatedIndex, $relatedIndexes, $relatedActivities, $requestActivity): void {
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

        foreach ((clone $query)->lazyByIdDesc(500) as $activity) {
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
        $selectedActivities->load('user:id,name,email');

        return (new LengthAwarePaginator(
            $selectedActivities,
            $groupCount,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page'],
        ))->withQueryString();
    }

    private function belongsToRequest(Activity $requestActivity, Activity $modelActivity): bool
    {
        $requestRoute = $requestActivity->details?->get('route');

        return filled($requestRoute)
            && $modelActivity->user_id === $requestActivity->user_id
            && $modelActivity->details?->get('route') === $requestRoute
            && abs(($modelActivity->created_at?->getTimestamp() ?? 0) - ($requestActivity->created_at?->getTimestamp() ?? 0)) <= 10;
    }

    private function shouldAggregateModelEvent(Request $request): bool
    {
        return $request === $this->requestBeingProcessed && $request->route() !== null;
    }

    private function hasRealModelChanges(Model $model): bool
    {
        return collect($model->getChanges())
            ->contains(fn (mixed $value, string $key): bool => ! $model->originalIsEquivalent($key));
    }

    private function addOrUpdatePendingModelEvent(array $modelEvent): void
    {
        $existingIndex = collect($this->pendingModelEvents)->search(
            fn (array $pending): bool => $pending['model_type'] === $modelEvent['model_type']
                && (string) $pending['model_id'] === (string) $modelEvent['model_id'],
        );

        if ($existingIndex === false) {
            $this->pendingModelEvents[] = $modelEvent;

            return;
        }

        $existing = $this->pendingModelEvents[$existingIndex];
        $this->pendingModelEvents[$existingIndex] = [
            ...$existing,
            'event' => $existing['event'] === 'created' ? 'created' : $modelEvent['event'],
            'model_label' => $modelEvent['model_label'],
            'model_number' => $modelEvent['model_number'],
            'company_id' => $modelEvent['company_id'] ?? $existing['company_id'],
            'new_data' => $modelEvent['new_data'],
        ];
    }

    private function modelKey(array $model, int $index): string
    {
        return ($model['model_type'] ?? 'model').'#'.($model['model_id'] ?? 'new').'@'.$index;
    }

    private function metrics(): array
    {
        $counts = Activity::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today')
            ->selectRaw("SUM(CASE WHEN source = 'model' THEN 1 ELSE 0 END) as model_count")
            ->selectRaw("SUM(CASE WHEN source = 'request' THEN 1 ELSE 0 END) as request_count")
            ->first();

        $requestWithModels = Activity::query()->where('source', 'request')->whereJsonLength('details->models', '>', 0)->count();

        return [
            'total' => $counts->total,
            'today' => $counts->today,
            'model' => $counts->model_count + $requestWithModels,
            'request' => $counts->request_count,
        ];
    }

    /**
     * Derive available model types at the database level instead of hydrating every activity row.
     *
     * Combines distinct values from the model_type column with model types stored inside the details->model_types JSON arrays of request-source activities.
     */
    private function availableModelTypes(): Collection
    {
        $directTypes = Activity::query()->whereNotNull('model_type')->distinct()->pluck('model_type');

        $jsonTypes = DB::table('activity_log')->whereJsonLength('details->model_types', '>', 0)->pluck('details')
            ->map(fn (string $raw) => (json_decode($raw, true)['model_types'] ?? []))->flatten()->filter()->unique();

        return $directTypes->merge($jsonTypes)->sort()->values();
    }

    /** Build complete, sanitized before/after snapshots for one model event. */
    private function modelChanges(string $event, Model $model): array
    {
        if ($event === 'created') {
            return ['attributes' => $this->sanitizeModelAttributes($model->getAttributes())];
        }

        if ($event === 'deleted') {
            return ['old' => $this->sanitizeModelAttributes($model->getAttributes())];
        }

        return [
            'attributes' => $this->sanitizeModelAttributes($model->getAttributes()),
            'old' => $this->sanitizeModelAttributes($model->getRawOriginal()),
        ];
    }

    /** Remove ignored/sensitive fields before writing model data to JSON. */
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

    private function modelCompanyId(Model $model, Request $request): ?int
    {
        if ($model instanceof Company) {
            return (int) $model->getKey();
        }

        if (array_key_exists('company_id', $model->getAttributes())) {
            $companyId = $model->getAttribute('company_id');

            return $companyId ? (int) $companyId : null;
        }

        return $this->requestCompanyId($request);
    }

    private function requestCompanyId(Request $request): ?int
    {
        if ($request->hasSession() && $request->session()->get('interface_mode') === 'management') {
            return null;
        }

        $companyId = config('active-company-id') ?? $request->cookie('active-company-id');

        return $companyId ? (int) $companyId : null;
    }

    /**
     * @return array{impersonated_user_id?: int}
     */
    private function impersonationProperties(User $actor, ?User $authenticatedUser): array
    {
        if (! $authenticatedUser || $actor->is($authenticatedUser)) {
            return [];
        }

        return ['impersonated_user_id' => (int) $authenticatedUser->getKey()];
    }

    /** Recursively sanitize request input while preserving useful structure. */
    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match(self::HIDDEN_ATTRIBUTE_PATTERN, $key) === 1) {
            return '[REDACTED]';
        }

        if ($value instanceof UploadedFile) {
            return ['file' => $value->getClientOriginalName(), 'size' => $value->getSize()];
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
