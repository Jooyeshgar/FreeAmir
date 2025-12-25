<?php

namespace App\Enums;

enum InvoiceAncillaryCostStatus: string
{
    case PENDING = 'pending';

    case APPROVED = 'approved';

    case UNAPPROVED = 'unapproved';

    case APPROVED_INACTIVE = 'approved_inactive';

    /**
     * Get translated label for the invoice/ancillary cost status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('pending'),
            self::APPROVED => __('approved'),
            self::UNAPPROVED => __('unapproved'),
            self::APPROVED_INACTIVE => __('approved inactive'),
        };
    }

    public function isApprovedInactive(): bool
    {
        return $this === self::APPROVED_INACTIVE;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
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
