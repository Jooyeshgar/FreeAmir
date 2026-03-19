<?php

namespace App\Enums;

enum ThursdayStatus: string
{
    case HOLIDAY = 'holiday';
    case FULL_DAY = 'full_day';
    case HALF_DAY = 'half_day';

    public function label(): string
    {
        return match ($this) {
            self::HOLIDAY => __('Holiday'),
            self::FULL_DAY => __('Full Day'),
            self::HALF_DAY => __('Half Day'),
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
