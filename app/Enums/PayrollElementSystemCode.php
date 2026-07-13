<?php

namespace App\Enums;

use ValueError;

enum PayrollElementSystemCode: int
{
    case CHILD_ALLOWANCE = 1;
    case HOUSING_ALLOWANCE = 2;
    case FOOD_ALLOWANCE = 3;
    case MARRIAGE_ALLOWANCE = 4;
    case OVERTIME = 5;
    case AUTO_OVERTIME = 6;
    case FRIDAY_PAY = 7;
    case HOLIDAY_PAY = 8;
    case MISSION_PAY = 9;
    case INSURANCE_EMP = 10;
    case INSURANCE_EMP2 = 11;
    case UNEMPLOYMENT_INS = 12;
    case INCOME_TAX = 13;
    case ABSENCE_DEDUCTION = 14;
    case OTHER = 15;
    case UNDERTIME = 16;

    public function valueName(): string
    {
        return $this->name;
    }

    public static function valueNames(): array
    {
        return array_map(fn (self $case) => $case->valueName(), self::cases());
    }

    public static function fromName(self|int|string $value): self
    {
        return self::tryFromName($value) ?? throw new ValueError(sprintf('"%s" is not a valid %s', (string) $value, self::class));
    }

    public static function tryFromName(self|int|string|null $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return self::tryFrom((int) $value);
        }

        foreach (self::cases() as $case) {
            if ($case->valueName() === $value || $case->name === $value) {
                return $case;
            }
        }

        return null;
    }
}
