<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';

    case PRE_INVOICE = 'pre_invoice';

    case APPROVED = 'approved';

    case UNAPPROVED = 'unapproved';

    case APPROVED_INACTIVE = 'approved_inactive';

    case REJECTED = 'rejected';

    case READY_TO_APPROVE = 'ready_to_approve';

    /**
     * Get translated label for the invoice/ancillary cost status.
     */
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
        };
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
