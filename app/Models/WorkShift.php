<?php

namespace App\Models;

use App\Enums\ThursdayStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'float_before',
        'float_after',
        'break',
        'thursday_status',
        'is_active',
    ];

    protected $casts = [
        'thursday_status' => ThursdayStatus::class,
        'is_active' => 'boolean',
        'float_before' => 'integer',
        'float_after' => 'integer',
        'break' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'work_shift_id');
    }
}
