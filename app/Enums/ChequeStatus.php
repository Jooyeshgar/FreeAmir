<?php

namespace App\Enums;

enum ChequeStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case RETURNED = 'returned';
    case CHECKOUT = 'checkout';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::ISSUED => __('Issued'),
            self::RETURNED => __('Returned'),
            self::CHECKOUT => __('Checkout'),
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
