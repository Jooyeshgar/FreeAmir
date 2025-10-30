<?php

namespace App\Enums;

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
    case SALES_REVENUE = 'SALES_REVENUE';
    // case INVENTORY = 'INVENTORY';
    case COST_OF_GOODS = 'COST_OF_GOODS';
    case RETURN_SALES = 'RETURN_SALES';

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
            // self::INVENTORY => __('Inventory'),
            self::COST_OF_GOODS => __('Cost of Goods Sold'),
            self::RETURN_SALES => __('Return Sales'),
            self::SALES_REVENUE => __('Sales Revenue'),
        };
    }
}
