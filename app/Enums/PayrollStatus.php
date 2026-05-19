<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case PendingManagerApproval = 'pending_manager_approval';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingManagerApproval => __('Pending Manager Approval'),
            self::Approved => __('Approved'),
            self::Paid => __('Paid'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge-ghost',
            self::PendingManagerApproval => 'badge-warning',
            self::Approved => 'badge-success',
            self::Paid => 'badge-info',
        };
    }

    public function transitionPermissionTo(self $to): ?string
    {
        return match ([$this, $to]) {
            [self::Draft, self::PendingManagerApproval] => 'salary.payrolls.transition.draft-to-pending-manager-approval',
            [self::PendingManagerApproval, self::Approved] => 'salary.payrolls.transition.pending-manager-approval-to-approved',
            [self::Approved, self::Paid] => 'salary.payrolls.transition.approved-to-paid',
            default => null,
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn (self $status) => ['value' => $status->value, 'label' => $status->label()], self::cases()),
            'label',
            'value'
        );
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
