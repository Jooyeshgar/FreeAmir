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
    case INVENTORY = 'INVENTORY';
    case SALES_REVENUE = 'SALES_REVENUE';
    case COST_OF_GOODS_SOLD = 'COST_OF_GOODS_SOLD';
    case SALES_RETURNS = 'SALES_RETURNS';
    case SERVICE_REVENUE = 'SERVICE_REVENUE';

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
            self::INVENTORY => __('Inventory'),
            self::COST_OF_GOODS_SOLD => __('Cost of Goods Sold'),
            self::SALES_RETURNS => __('Sales Returns'),
            self::SALES_REVENUE => __('Sales Revenue'),
            self::SERVICE_REVENUE => __('Service Revenue'),
        };
    }
}
