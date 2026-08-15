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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lab404\Impersonate\Services\ImpersonateManager;

class ActivityLogService
{
    private const HIDDEN_ATTRIBUTE_PATTERN = '/password|passphrase|remember_token|token|secret|private_key|certificate/i';

    private const IGNORED_MODEL_ATTRIBUTES = ['created_at', 'updated_at'];

    private ?User $cachedActor = null;

    private bool $actorCached = false;

    private ?Request $actorRequest = null;

    private int|string|null $actorAuthenticatedUserId = null;

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

    /**
     * Record a successful state-changing HTTP request.
     */
    public function recordRequest(Request $request, ?User $actor = null, ?User $authenticatedUser = null): void
    {
        $authenticatedUser ??= $request->user();
        $actor ??= $this->resolveActor($authenticatedUser);

        if (! $actor || ! config('activitylog.enabled')) {
            return;
        }

        $routeName = $request->route()?->getName();
        $method = strtoupper($request->method());

        activity('request')->causedBy($actor)->event(strtolower($method))->withProperties([
            'route' => $routeName,
            'method' => $method,
            'path' => '/'.ltrim($request->path(), '/'),
            'company_id' => $this->requestCompanyId($request),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '…'),
            'request_input' => $this->sanitize($request->all()),
            ...$this->impersonationProperties($actor, $authenticatedUser),
        ])->log(trim($method.' '.($routeName ?: '/'.ltrim($request->path(), '/'))));
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

        $changes = $this->modelChanges($event, $model);

        if ($event === 'updated' && $changes['attributes'] === []) {
            return;
        }

        $request ??= request();

        activity('model')->causedBy($actor)->performedOn($model)->event($event)->withProperties(array_filter([
            ...$changes,
            'model_label' => $this->modelLabel($model),
            'company_id' => $this->modelCompanyId($model, $request),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            ...$this->impersonationProperties($actor, $authenticatedUser),
        ], fn (mixed $value) => $value !== null))->log($event);
    }

    /**
     * Build the filtered platform audit-log payload.
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
            ->when($filters['model_type'] ?? null, fn ($query, string $modelType) => $query->where('model_type', $modelType))
            ->when($filters['company_id'] ?? null, fn ($query, int|string $companyId) => $query->where('details->company_id', (int) $companyId))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date));

        $activities = $this->paginateGroupedActivities($query);
        $impersonatedUserIds = $activities->getCollection()
            ->map(fn (Activity $activity) => $activity->details?->get('impersonated_user_id'))->filter()->unique();

        return [
            'activities' => $activities,
            'metrics' => $this->metrics(),
            'users' => User::query()->whereIn('id', Activity::query()->whereNotNull('user_id')->select('user_id'))->orderBy('name')->get(['id', 'name', 'email']),
            'impersonatedUsers' => User::query()->whereIn('id', $impersonatedUserIds)->get(['id', 'name', 'email'])->keyBy('id'),
            'companies' => Company::query()->orderByDesc('fiscal_year')->orderBy('name')->get(['id', 'name', 'fiscal_year']),
            'modelTypes' => Activity::query()->whereNotNull('model_type')->distinct()->orderBy('model_type')->pluck('model_type'),
            'filters' => $filters,
        ];
    }

    private function paginateGroupedActivities(Builder $query): LengthAwarePaginator
    {
        $perPage = 25;
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

    /**
     * @return array{total: int, today: int, model: int, request: int}
     */
    private function metrics(): array
    {
        return [
            'total' => Activity::query()->count(),
            'today' => Activity::query()->whereDate('created_at', Carbon::today())->count(),
            'model' => Activity::query()->where('source', 'model')->count(),
            'request' => Activity::query()->where('source', 'request')->count(),
        ];
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

        $attributes = $this->sanitizeModelAttributes($model->getChanges());
        $old = [];

        foreach (array_keys($attributes) as $key) {
            $old[$key] = $this->sanitizeValue($model->getRawOriginal($key));
        }

        return ['attributes' => $attributes, 'old' => $old];
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
