<?php

namespace App\Enums;

enum InvoiceAncillaryCostStatus: string
{
    case PENDING = 'pending';

    case APPROVED = 'approved';

    case UNAPPROVED = 'unapproved';

    /**
     * Get translated label for the invoice/ancillary cost status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('pending'),
            self::APPROVED => __('approved'),
            self::UNAPPROVED => __('unapproved'),
        };
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
