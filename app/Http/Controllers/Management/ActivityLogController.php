<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Company;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityLogController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function index(Request $request): View
    {
        $request->session()->put('interface_mode', 'management');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'user_id' => ['nullable', 'integer'],
            'model_type' => ['nullable', 'string', 'max:255'],
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

    /**
     * @return array<string, array{label: string, style: string}>
     */
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

    /**
     * @param  array{total: int, today: int, model: int, request: int}  $metrics
     * @return array<int, array{label: string, value: string, accent: string, icon: string}>
     */
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

    /**
     * @param  Collection<int, Company>  $companyLookup
     * @param  Collection<int, User>  $impersonatedUsers
     * @return array<string, mixed>
     */
    private function activityRow(Activity $activity, Collection $companyLookup, Collection $impersonatedUsers): array
    {
        $details = $activity->details ?? collect();
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
        if ($canonicalAction === 'created') {
            $changeKeys = collect();
        }
        $action = $this->actionOptions()[$canonicalAction] ?? [
            'label' => __(ucfirst($activity->action ?? 'activity')),
            'style' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300',
        ];
        $modelTitle = __(class_basename($activity->model_type ?? 'Activity'));
        $route = $details->get('route');

        return [
            'id' => $activity->id,
            'userId' => $activity->user_id,
            'isRequest' => $isRequest,
            'actionLabel' => $action['label'],
            'actionStyle' => $action['style'],
            'userInitial' => mb_strtoupper(mb_substr($activity->user?->name ?? '?', 0, 1)),
            'userName' => $activity->user?->name ?? __('System'),
            'userUrl' => $activity->user ? route('users.show', $activity->user) : null,
            'companyLabel' => $company ? $company->name.' - '.localizeNumber($company->fiscal_year) : null,
            'title' => $isRequest ? ($route ?: $activity->description) : $modelTitle,
            'titleDetail' => $isRequest ? null : $details->get('model_label', '#'.$activity->model_id),
            'requestMethod' => $isRequest
                ? strtoupper((string) ($details->get('method') ?: $activity->action))
                : null,
            'route' => $route,
            'contextLabel' => $route ?: ($isRequest ? $activity->description : $modelTitle.' #'.$activity->model_id),
            'modelContextLabel' => $isRequest ? null : $modelTitle.' #'.$activity->model_id,
            'modelId' => $isRequest ? null : $activity->model_id,
            'ipAddress' => $details->get('ip_address'),
            'impersonatedUserName' => $impersonatedUser?->name,
            'createdAt' => $activity->created_at?->toAtomString(),
            'createdAtTimestamp' => $activity->created_at?->getTimestamp(),
            'createdAtLabel' => formatDateTime($activity->created_at),
            'hasDetails' => $isRequest || $changeKeys->isNotEmpty(),
            'requestContext' => $isRequest ? [
                ['label' => __('Route'), 'value' => $details->get('route') ?: '—'],
                ['label' => __('Method'), 'value' => $details->get('method') ?: '—'],
                ['label' => __('Path'), 'value' => $details->get('path') ?: '—'],
                ['label' => __('IP address'), 'value' => $details->get('ip_address') ?: '—'],
                ['label' => __('User agent'), 'value' => $details->get('user_agent') ?: '—'],
            ] : [],
            'requestInput' => filled($details->get('request_input'))
                ? (json_encode($details->get('request_input'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '—')
                : null,
            'changes' => $changeKeys->map(fn (string $key): array => [
                'field' => $key,
                'old' => $this->formatValue($old->get($key)),
                'new' => $this->formatValue($attributes->get($key)),
            ]),
        ];
    }

    /**
     * Merge a write request with the model event it caused when both audit rows
     * belong to the same actor, named route, and moment.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function mergeRequestRows(Collection $rows): Collection
    {
        $rows = $rows->values();
        $mergedIndexes = [];

        foreach ($rows as $requestIndex => $requestRow) {
            if (! $requestRow['isRequest'] || blank($requestRow['route'])) {
                continue;
            }

            $modelIndex = $rows->search(function (array $modelRow, int $index) use ($requestIndex, $requestRow, $mergedIndexes): bool {
                return $index > $requestIndex
                    && ! isset($mergedIndexes[$index])
                    && ! $modelRow['isRequest']
                    && $modelRow['userId'] === $requestRow['userId']
                    && $modelRow['route'] === $requestRow['route']
                    && abs(($modelRow['createdAtTimestamp'] ?? 0) - ($requestRow['createdAtTimestamp'] ?? 0)) <= 10;
            });

            if ($modelIndex === false) {
                continue;
            }

            $modelRow = $rows[$modelIndex];
            $modelRow['requestMethod'] = $requestRow['requestMethod'];
            $modelRow['requestContext'] = $requestRow['requestContext'];
            $modelRow['requestInput'] = $requestRow['requestInput'];
            $modelRow['hasDetails'] = true;
            $rows[$modelIndex] = $modelRow;
            $mergedIndexes[$requestIndex] = true;
        }

        return $rows->reject(fn (array $row, int $index): bool => isset($mergedIndexes[$index]))->values();
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
