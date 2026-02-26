<?php

namespace App\Enums;

enum EmployeeNationality: string
{
    case IRANIAN = 'iranian';
    case FOREIGN = 'foreign';

    public function label(): string
    {
        return match ($this) {
            self::IRANIAN => __('Iranian'),
            self::FOREIGN => __('Foreign'),
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
