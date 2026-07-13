<?php

namespace App\Enums;

use ValueError;

enum PayrollElementCalcType: int
{
    case FIXED = 1;
    case FORMULA = 2;
    case PERCENTAGE = 3;
    case DAILY = 4;

    public function label(): string
    {
        return match ($this) {
            self::FIXED => __('Fixed'),
            self::FORMULA => __('Formula'),
            self::PERCENTAGE => __('Percentage'),
            self::DAILY => __('Daily'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::FIXED => 'fixed',
            self::FORMULA => 'formula',
            self::PERCENTAGE => 'percentage',
            self::DAILY => 'daily',
        };
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
