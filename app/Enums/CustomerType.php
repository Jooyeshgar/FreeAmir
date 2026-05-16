<?php

namespace App\Enums;

enum CustomerType: string
{
    case INDIVIDUAL = 'individual';
    case LEGAL_ENTITY = 'legal_entity';
    case CIVIL_PARTNERSHIP = 'civil_partnership';
    case FOREIGN_NATIONAL = 'foreign_national';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => __('Individual'),
            self::LEGAL_ENTITY => __('Legal Entity'),
            self::CIVIL_PARTNERSHIP => __('Civil Partnership'),
            self::FOREIGN_NATIONAL => __('Foreign National'),
        };
    }

    public static function options(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = $case->label();

            return $carry;
        }, []);
    }
}
