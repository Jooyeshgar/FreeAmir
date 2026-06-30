<?php

namespace App\Models;

use AliMousavi\Filoquent\Traits\Filterable;
use App\Enums\PersonnelRequestType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelRequest extends Model
{
    use Filterable;
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'company_id',
        'request_type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approved_by',
        'payroll_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'request_type' => PersonnelRequestType::class,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function scopeOfType(Builder $query, PersonnelRequestType $type): Builder
    {
        return $query->where('request_type', $type);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
