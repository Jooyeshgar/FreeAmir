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
        'company_id',
        'monthly_attendance_id',
        'log_date',
        'entry_time',
        'exit_time',
        'worked',
        'delay',
        'early_leave',
        'overtime',
        'mission',
        'paid_leave',
        'unpaid_leave',
        'is_friday',
        'is_holiday',
        'log_type',
        'is_manual',
        'description',
    ];

    protected $casts = [
        'log_date' => 'date',
        'is_manual' => 'boolean',
        'is_friday' => 'boolean',
        'is_holiday' => 'boolean',
        'worked' => 'integer',
        'delay' => 'integer',
        'early_leave' => 'integer',
        'overtime' => 'integer',
        'mission' => 'integer',
        'paid_leave' => 'integer',
        'unpaid_leave' => 'integer',
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
