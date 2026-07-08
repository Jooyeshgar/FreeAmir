<?php

namespace App\Enums;

use ValueError;

enum PayrollElementCategory: int
{
    case EARNING = 1;
    case DEDUCTION = 2;

    public function label(): string
    {
        return match ($this) {
            self::EARNING => __('Earning'),
            self::DEDUCTION => __('Deduction'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::EARNING => 'earning',
            self::DEDUCTION => 'deduction',
        };
    }

    public function isEarning(): bool
    {
        return $this === self::EARNING;
    }

    public function isDeduction(): bool
    {
        return $this === self::DEDUCTION;
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
