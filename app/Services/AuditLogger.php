<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    private const SENSITIVE_KEYS = ['password', 'remember_token', 'token', 'api_token', 'private_key', 'certificate', 'secret'];

    /**
     * Register one listener for each persistence event across Eloquent models.
     */
    public static function register(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload) use ($event): void {
                $model = $payload[0] ?? null;

                if (! $model instanceof Model || $model instanceof AuditLog || ! auth()->check()) {
                    return;
                }

                self::recordModelEvent($event, $model);
            });
        }
    }

    public static function record(string $action, ?Model $subject = null, array $data = []): void
    {
        if (! auth()->check()) {
            return;
        }

        try {
            $request = request();

            AuditLog::query()->create([
                'user_id' => auth()->id(),
                'company_id' => self::companyId($subject),
                'action' => $action,
                'method' => $request->method(),
                'route_name' => $request->route()?->getName(),
                'url' => $request->route() ? '/'.ltrim($request->path(), '/') : 'console',
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey() ? (int) $subject->getKey() : null,
                'request_data' => self::sanitize($data),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Audit trail entry could not be persisted.', [
                'action' => $action,
                'exception' => $exception,
            ]);
        }
    }

    private static function recordModelEvent(string $event, Model $model): void
    {
        $changes = $event === 'updated' ? $model->getChanges() : $model->getAttributes();
        $data = ['changes' => $changes];

        if ($event === 'updated') {
            $data['original'] = collect(array_keys($changes))->mapWithKeys(fn (string $key): array => [$key => $model->getOriginal($key)])->all();
        }

        self::record(class_basename($model).'.'.$event, $model, $data);
    }

    private static function companyId(?Model $subject): ?int
    {
        if ($subject instanceof Company) {
            return (int) $subject->getKey();
        }

        $companyId = $subject?->getAttribute('company_id');

        if (! $companyId && auth()->user()?->can(config('admin.access_ability', 'admin.access'))) {
            $companyId = request()->hasSession() ? request()->session()->get(config('admin.company_filter_session_key', 'admin.company_filter_id')) : null;

            return $companyId ? (int) $companyId : null;
        }

        $companyId ??= request()->cookie('active-company-id') ?? config('active-company-id');

        return $companyId ? (int) $companyId : null;
    }

    private static function sanitize(array $data): ?array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitize($value) ?? [];
            } elseif (is_string($value) && mb_strlen($value) > 2000) {
                $sanitized[$key] = mb_substr($value, 0, 2000).'… [truncated]';
            } elseif (is_scalar($value) || $value === null || $value instanceof \JsonSerializable || $value instanceof \BackedEnum) {
                $sanitized[$key] = $value;
            }
        }

        if (strlen((string) json_encode($sanitized)) > 65_535) {
            return [
                '_audit_note' => 'Audit payload omitted because it exceeded 64 KB.',
                '_keys' => array_slice(array_keys($sanitized), 0, 100),
            ];
        }

        return $sanitized === [] ? null : $sanitized;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return collect(self::SENSITIVE_KEYS)->contains(fn (string $sensitive): bool => $normalized === $sensitive || str_ends_with($normalized, "_{$sensitive}"));
    }
}
