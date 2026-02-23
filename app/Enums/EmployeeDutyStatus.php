<?php

namespace App\Enums;

enum EmployeeDutyStatus: string
{
    case LIABLE = 'liable';
    case COMPLETED = 'completed';
    case IN_PROGRESS = 'in_progress';
    case EXEMPT = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::LIABLE => __('Liable'),
            self::COMPLETED => __('Completed'),
            self::IN_PROGRESS => __('In Progress'),
            self::EXEMPT => __('Exempt'),
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
