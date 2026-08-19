<?php

namespace App\Enums;

use ValueError;

enum InvoiceStatus: int
{
    case PENDING = 1;

    case PRE_INVOICE = 2;

    case APPROVED = 3;

    case UNAPPROVED = 4;

    case APPROVED_INACTIVE = 5;

    case REJECTED = 6;

    case READY_TO_APPROVE = 7;

    case PARTIALLY_PAID = 8;

    case PAID = 9;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('pending'),
            self::PRE_INVOICE => __('Pre Invoice'),
            self::APPROVED => __('approved'),
            self::UNAPPROVED => __('unapproved'),
            self::APPROVED_INACTIVE => __('approved inactive'),
            self::REJECTED => __('rejected'),
            self::READY_TO_APPROVE => __('ready to approve'),
            self::PARTIALLY_PAID => __('Partially paid'),
            self::PAID => __('Paid'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::PENDING => 'pending',
            self::PRE_INVOICE => 'pre_invoice',
            self::APPROVED => 'approved',
            self::UNAPPROVED => 'unapproved',
            self::APPROVED_INACTIVE => 'approved_inactive',
            self::REJECTED => 'rejected',
            self::READY_TO_APPROVE => 'ready_to_approve',
            self::PARTIALLY_PAID => 'partially_paid',
            self::PAID => 'paid',
        };
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

    public static function approvedOrSettled(): array
    {
        return [self::APPROVED, self::PARTIALLY_PAID, self::PAID];
    }

    public function isApprovedOrSettled(): bool
    {
        return in_array($this, self::approvedOrSettled(), true);
    }

    public function isPartiallyPaid(): bool
    {
        return $this === self::PARTIALLY_PAID;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isReadyToApprove(): bool
    {
        return $this === self::READY_TO_APPROVE;
    }

    public function isPreInvoice(): bool
    {
        return $this === self::PRE_INVOICE;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isApprovedInactive(): bool
    {
        return $this === self::APPROVED_INACTIVE;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isUnapproved(): bool
    {
        return $this === self::UNAPPROVED;
    }
}
