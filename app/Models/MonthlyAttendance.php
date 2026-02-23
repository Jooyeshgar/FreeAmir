<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'year',
        'month',
        'work_days',
        'present_days',
        'absent_days',
        'overtime',
        'mission_days',
        'paid_leave_days',
        'unpaid_leave_days',
        'friday',
        'holiday',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'work_days' => 'integer',
        'present_days' => 'integer',
        'absent_days' => 'integer',
        'overtime' => 'integer',
        'mission_days' => 'integer',
        'paid_leave_days' => 'integer',
        'unpaid_leave_days' => 'integer',
        'friday' => 'integer',
        'holiday' => 'integer',
    ];

    /** Jalali month names indexed 1–12 */
    public const MONTH_NAMES = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
        4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
        10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'monthly_attendance_id');
    }

    /** Human-readable Jalali month label */
    public function getMonthNameAttribute(): string
    {
        return self::MONTH_NAMES[$this->month] ?? (string) $this->month;
    }
}
