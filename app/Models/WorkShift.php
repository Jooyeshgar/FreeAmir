<?php

namespace App\Models;

use App\Enums\ThursdayStatus;
use App\Models\Scopes\FiscalYearScope;
use Carbon\Carbon;
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
        'thursday_exit_time',
        'is_active',
    ];

    protected $casts = [
        'thursday_status' => ThursdayStatus::class,
        'is_active' => 'boolean',
        'float_before' => 'integer',
        'float_after' => 'integer',
        'break' => 'integer',
    ];

    public function getDurationAttribute(): int
    {
        $start = Carbon::createFromFormat('H:i:s', $this->start_time);
        $end = Carbon::createFromFormat('H:i:s', $this->end_time);

        $duration = $end->diffInMinutes($start);
        $duration -= $this->break;

        return max(0, $duration);
    }

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'work_shift_id');
    }
}
