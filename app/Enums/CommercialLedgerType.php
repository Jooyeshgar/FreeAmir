<?php

namespace App\Enums;

enum CommercialLedgerType: int
{
    case ALL_SUBSIDIARY = 1;
    case ALL_DETAILED = 2;
    case VOUCHER_SUBSIDIARY = 3;
    case VOUCHER_DETAILED = 4;
    case MONTHLY_OPENING_SUBSIDIARY = 5;
    case MONTHLY_OPENING_DETAILED = 6;
    case MONTHLY_GENERAL = 7;

    public function label(): string
    {
        return match ($this) {
            self::ALL_SUBSIDIARY => __('Commercial Ledger without aggregation (All Vouchers) - Subsidiary Account Level'),
            self::ALL_DETAILED => __('Commercial Ledger without aggregation (All Vouchers) - Detailed Account Level'),
            self::VOUCHER_SUBSIDIARY => __('Commercial Ledger with voucher-level aggregation - Subsidiary Account Level'),
            self::VOUCHER_DETAILED => __('Commercial Ledger with voucher-level aggregation - Detailed Account Level'),
            self::MONTHLY_OPENING_SUBSIDIARY => __('Monthly aggregation with opening voucher breakdown - Subsidiary Account Level'),
            self::MONTHLY_OPENING_DETAILED => __('Monthly aggregation with opening voucher breakdown - Detailed Account Level'),
            self::MONTHLY_GENERAL => __('Monthly aggregation without opening voucher breakdown - General Account Level'),
        };
    }

    public function isGeneralLevel(): bool
    {
        return $this === self::MONTHLY_GENERAL;
    }

    public function isDetailedLevel(): bool
    {
        return in_array($this, [self::ALL_DETAILED, self::VOUCHER_DETAILED, self::MONTHLY_OPENING_DETAILED], true);
    }

    public function isVoucherAggregation(): bool
    {
        return in_array($this, [self::VOUCHER_SUBSIDIARY, self::VOUCHER_DETAILED], true);
    }

    public function isMonthlyAggregation(): bool
    {
        return in_array($this, [self::MONTHLY_OPENING_SUBSIDIARY, self::MONTHLY_OPENING_DETAILED, self::MONTHLY_GENERAL], true);
    }

    public function breaksDownOpeningVouchers(): bool
    {
        return in_array($this, [self::MONTHLY_OPENING_SUBSIDIARY, self::MONTHLY_OPENING_DETAILED], true);
    }
}
