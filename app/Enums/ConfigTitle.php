<?php

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum ConfigTitle: string
{
    case CUST_SUBJECT = 'CUST_SUBJECT';
    case CASH_BOOK = 'CASH_BOOK';
    case BANK = 'BANK';
    case CASH = 'CASH';
    case INCOME = 'INCOME';
    case SELL_DISCOUNT = 'SELL_DISCOUNT';
    case BUY_DISCOUNT = 'BUY_DISCOUNT';
    case SELL_VAT = 'SELL_VAT';
    case BUY_VAT = 'BUY_VAT';
    case PRODUCT = 'PRODUCT';

    // Get translated label
    public function label(): string
    {
        return match ($this) {
            self::CUST_SUBJECT => __('Customers'),
            self::CASH_BOOK => __('Cash balances'),
            self::BANK => __('Banks'),
            self::CASH => __('Cash'),
            self::INCOME => __('Income'),
            self::SELL_DISCOUNT => __('Sell Discount'),
            self::BUY_DISCOUNT => __('Buy Discount'),
            self::SELL_VAT => __('Sell Vat'),
            self::BUY_VAT => __('Buy Vat'),
            self::PRODUCT => __('Products'),
        };
    }
}
