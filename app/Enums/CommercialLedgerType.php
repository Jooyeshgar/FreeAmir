<?php

namespace App\Enums;

enum CommercialLedgerType: string
{
    case ALL_SUBSIDIARY = 'all_subsidiary';
    case ALL_GENERAL = 'all_general';
    case VOUCHER_SUBSIDIARY = 'voucher_subsidiary';
    case VOUCHER_GENERAL = 'voucher_general';
    case MONTHLY_OPENING_SUBSIDIARY = 'monthly_opening_subsidiary';
    case MONTHLY_OPENING_GENERAL = 'monthly_opening_general';
    case MONTHLY_GENERAL = 'monthly_general';

    public function label(): string
    {
        return match ($this) {
            self::ALL_SUBSIDIARY => __('Commercial Ledger without aggregation (All Vouchers) - Subsidiary Account Level'),
            self::ALL_GENERAL => __('Commercial Ledger without aggregation (All Vouchers) - General Account Level'),
            self::VOUCHER_SUBSIDIARY => __('Commercial Ledger with voucher-level aggregation - Subsidiary Account Level'),
            self::VOUCHER_GENERAL => __('Commercial Ledger with voucher-level aggregation - General Account Level'),
            self::MONTHLY_OPENING_SUBSIDIARY => __('Monthly aggregation with opening voucher breakdown - Subsidiary Account Level'),
            self::MONTHLY_OPENING_GENERAL => __('Monthly aggregation with opening voucher breakdown - General Account Level'),
            self::MONTHLY_GENERAL => __('Monthly aggregation without opening voucher breakdown - General Account Level'),
        };
    }

    public function isGeneralLevel(): bool
    {
        return in_array($this, [self::ALL_GENERAL, self::VOUCHER_GENERAL, self::MONTHLY_OPENING_GENERAL, self::MONTHLY_GENERAL], true);
    }

    public function isVoucherAggregation(): bool
    {
        return in_array($this, [self::VOUCHER_SUBSIDIARY, self::VOUCHER_GENERAL], true);
    }

    public function isMonthlyAggregation(): bool
    {
        return in_array($this, [self::MONTHLY_OPENING_SUBSIDIARY, self::MONTHLY_OPENING_GENERAL, self::MONTHLY_GENERAL], true);
    }

    public function breaksDownOpeningVouchers(): bool
    {
        return in_array($this, [self::MONTHLY_OPENING_SUBSIDIARY, self::MONTHLY_OPENING_GENERAL], true);
    }
}
