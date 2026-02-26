<?php

namespace App\Enums;

enum EmployeeInsuranceType: string
{
    case SOCIAL_SECURITY = 'social_security';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SOCIAL_SECURITY => __('Social Security'),
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
