<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'subject_id',
        'month',
        'budget_type',
        'forecast_amount',
    ];

    protected $casts = [
        'month' => 'integer',
        'forecast_amount' => 'decimal:2',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (MonthlyBudget $budget) {
            $budget->company_id ??= getActiveCompany();
        });
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
