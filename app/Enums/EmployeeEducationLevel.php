<?php

namespace App\Enums;

enum EmployeeEducationLevel: string
{
    case BELOW_DIPLOMA = 'below_diploma';
    case DIPLOMA = 'diploma';
    case ASSOCIATE = 'associate';
    case BACHELOR = 'bachelor';
    case MASTER = 'master';
    case PHD = 'phd';

    public function label(): string
    {
        return match ($this) {
            self::BELOW_DIPLOMA => __('Below Diploma'),
            self::DIPLOMA => __('Diploma'),
            self::ASSOCIATE => __('Associate'),
            self::BACHELOR => __('Bachelor'),
            self::MASTER => __('Master'),
            self::PHD => __('PhD'),
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
