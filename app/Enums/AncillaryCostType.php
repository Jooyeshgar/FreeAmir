<?php

namespace App\Enums;

enum AncillaryCostType: string
{
    case Shipping = 'Shipping';
    case Insurance = 'Insurance';
    case Customs = 'Customs';
    case Taxes = 'Taxes';
    case Loading = 'Loading';
    case Other = 'Other';

    // Get translated label
    public function label(): string
    {
        return match ($this) {
            self::Shipping => __('Transportation Costs'),
            self::Insurance => __('Shipping Insurance'),
            self::Customs => __('Customs Fees And Import Duties'),
            self::Taxes => __('Non Refundable Taxes'),
            self::Loading => __('Loading And Unloading Costs'),
            self::Other => __('Other Costs'),
        };
    }

    public static function labels(): array
    {
        return array_map(
            fn(self $case) => $case->label(),
            self::cases()
        );
    }

    public static function options(): array
    {
        return array_combine(
            self::values(),
            self::labels()
        );
    }

    public static function values(): array
    {
        return array_map(
            fn (self $case) => $case->value,
            self::cases()
        );
    }
}
