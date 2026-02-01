<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'vat',
        'sstid',
        'company_id',
        'subject_id',
        'cogs_subject_id',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($model) {
            $model->company_id ??= getActiveCompany();
        });
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'group', 'id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function cogsSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'cogs_subject_id');
    }
}
