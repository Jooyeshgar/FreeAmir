<?php

namespace App\Enums;

use ValueError;

enum PayrollStatus: int
{
    case Draft = 1;
    case PendingManagerApproval = 2;
    case Approved = 3;
    case Paid = 4;

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::PendingManagerApproval => __('Pending Manager Approval'),
            self::Approved => __('Approved'),
            self::Paid => __('Paid'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::Draft => 'draft',
            self::PendingManagerApproval => 'pending_manager_approval',
            self::Approved => 'approved',
            self::Paid => 'paid',
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
            array_map(fn (self $status) => ['value' => $status->valueName(), 'label' => $status->label()], self::cases()),
            'label',
            'value'
        );
    }

    public static function values(): array
    {
        return self::valueNames();
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->valueName(), self::cases());
    }

    public static function fromName(self|int|string $value): self
    {
        return self::tryFromName($value) ?? throw new ValueError(sprintf('"%s" is not a valid %s', (string) $value, self::class));
    }

    public static function tryFromName(self|int|string|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::tryFrom((int) $value);
        }

        foreach (self::cases() as $case) {
            if ($case->valueName() === $value || $case->name === $value) {
                return $case;
            }
        }

        return null;
    }
}
