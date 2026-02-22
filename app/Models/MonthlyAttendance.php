<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'work_days',
        'present_days',
        'absent_days',
        'overtime_hours',
        'mission_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'sick_leave_days',
        'friday_hours',
        'holiday_hours',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'work_days' => 'integer',
        'present_days' => 'integer',
        'absent_days' => 'integer',
        'overtime_hours' => 'decimal:2',
        'mission_days' => 'integer',
        'paid_leave_days' => 'integer',
        'unpaid_leave_days' => 'integer',
        'sick_leave_days' => 'integer',
        'friday_hours' => 'decimal:2',
        'holiday_hours' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'monthly_attendance_id');
    }
}
