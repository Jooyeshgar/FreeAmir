<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_MANAGER_APPROVAL = 'pending_manager_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'company_id',
        'employee_id',
        'decree_id',
        'monthly_attendance_id',
        'year',
        'month',
        'total_earnings',
        'total_deductions',
        'net_payment',
        'employer_insurance',
        'tax_base_amount',
        'income_tax_amount',
        'issue_date',
        'status',
        'accounting_voucher_id',
        'description',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'total_earnings' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_payment' => 'decimal:2',
        'employer_insurance' => 'decimal:2',
        'issue_date' => 'datetime',
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

    public function decree(): BelongsTo
    {
        return $this->belongsTo(SalaryDecree::class, 'decree_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'payroll_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PayrollStatusHistory::class, 'payroll_id')
            ->orderBy('changed_at')
            ->orderBy('id');
    }

    public function personnelRequests(): HasMany
    {
        return $this->hasMany(PersonnelRequest::class, 'payroll_id');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_PENDING_MANAGER_APPROVAL => __('Pending Manager Approval'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_PAID => __('Paid'),
        ];
    }

    public static function transitionPermissions(): array
    {
        return [
            self::STATUS_DRAFT => [
                self::STATUS_PENDING_MANAGER_APPROVAL => 'salary.payrolls.transition.draft-to-pending-manager-approval',
            ],
            self::STATUS_PENDING_MANAGER_APPROVAL => [
                self::STATUS_APPROVED => 'salary.payrolls.transition.pending-manager-approval-to-approved',
            ],
            self::STATUS_APPROVED => [
                self::STATUS_PAID => 'salary.payrolls.transition.approved-to-paid',
            ],
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'badge-ghost',
            self::STATUS_PENDING_MANAGER_APPROVAL => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_PAID => 'badge-info',
            default => 'badge-neutral',
        };
    }

    public function transitionPermissionTo(string $toStatus): ?string
    {
        return self::transitionPermissions()[$this->status][$toStatus] ?? null;
    }
}
