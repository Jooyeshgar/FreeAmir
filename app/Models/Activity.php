<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    protected $casts = [
        'details' => 'collection',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Keep the package logger compatible while storing its fixed actor in user_id.
     */
    public function causer(): MorphTo
    {
        return $this->morphTo('causer', 'causer_type', 'user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'model_type', 'model_id');
    }

    public function getCauserAttribute(): ?User
    {
        return $this->user;
    }

    public function getCauserIdAttribute(): ?int
    {
        $userId = $this->getAttribute('user_id');

        return $userId === null ? null : (int) $userId;
    }

    public function setCauserIdAttribute(int|string|null $userId): void
    {
        $this->setAttribute('user_id', $userId);
    }

    public function setCauserTypeAttribute(mixed $causerType): void
    {
        // The application only allows users to cause activity.
    }

    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $query->where('user_id', $causer->getKey());
    }

    public function scopeInLog(Builder $query, ...$logNames): Builder
    {
        $sources = is_array($logNames[0] ?? null) ? $logNames[0] : $logNames;

        return $query->whereIn('source', $sources);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('action', $event);
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('model_type', $subject->getMorphClass())->where('model_id', $subject->getKey());
    }

    public function getLogNameAttribute(): ?string
    {
        return $this->getAttribute('source');
    }

    public function setLogNameAttribute(?string $source): void
    {
        $this->setAttribute('source', $source);
    }

    public function getEventAttribute(): ?string
    {
        return $this->getAttribute('action');
    }

    public function setEventAttribute(?string $action): void
    {
        $this->setAttribute('action', $action);
    }

    public function getSubjectTypeAttribute(): ?string
    {
        return $this->getAttribute('model_type');
    }

    public function setSubjectTypeAttribute(?string $modelType): void
    {
        $this->setAttribute('model_type', $modelType);
    }

    public function getSubjectIdAttribute(): int|string|null
    {
        return $this->getAttribute('model_id');
    }

    public function setSubjectIdAttribute(int|string|null $modelId): void
    {
        $this->setAttribute('model_id', $modelId);
    }

    public function getPropertiesAttribute(): ?Collection
    {
        return $this->getAttribute('details');
    }

    public function setPropertiesAttribute(mixed $details): void
    {
        $this->setAttribute('details', $details);
    }
}
