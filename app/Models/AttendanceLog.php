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
        'remote_work',
        'delay',
        'early_leave',
        'approved_overtime',
        'overtime',
        'auto_overtime',
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
        'remote_work' => 'integer',
        'delay' => 'integer',
        'early_leave' => 'integer',
        'approved_overtime' => 'integer',
        'overtime' => 'integer',
        'auto_overtime' => 'integer',
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
