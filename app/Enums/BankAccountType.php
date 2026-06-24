<?php

namespace App\Enums;

enum BankAccountType: int
{
    case CURRENT = 1;
    case SAVINGS = 2;
    case INTEREST_FREE_LOAN = 3;
    case OTHER = 4;

    public function label(): string
    {
        return match ($this) {
            self::CURRENT => __('current'),
            self::SAVINGS => __('savings'),
            self::INTEREST_FREE_LOAN => __('interest free loan'),
            self::OTHER => __('other'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::CURRENT => 'current',
            self::SAVINGS => 'savings',
            self::INTEREST_FREE_LOAN => 'interest_free_loan',
            self::OTHER => 'other',
        };
    }
}
