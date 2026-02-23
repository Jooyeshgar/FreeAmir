<?php

namespace App\Enums;

enum EmployeeEmploymentType: string
{
    case PERMANENT = 'permanent';
    case CONTRACT = 'contract';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PERMANENT => __('Permanent'),
            self::CONTRACT => __('Contract'),
            self::OTHER => __('Other'),
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
