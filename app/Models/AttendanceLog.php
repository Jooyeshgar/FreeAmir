<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'monthly_attendance_id',
        'log_date',
        'entry_time',
        'exit_time',
        'log_type',
        'is_manual',
        'description',
    ];

    protected $casts = [
        'log_date' => 'date',
        'is_manual' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function monthlyAttendance(): BelongsTo
    {
        return $this->belongsTo(MonthlyAttendance::class, 'monthly_attendance_id');
    }
}
