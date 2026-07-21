<?php

namespace App\Enums;

use ValueError;

enum BankAccountType: int
{
    case CURRENT = 1;
    case SAVINGS = 2;
    case INTEREST_FREE_LOAN = 3;
    case OTHER = 4;

    public function label(): string
    {
        return match ($this) {
            self::CURRENT => __('current'),
            self::SAVINGS => __('savings'),
            self::INTEREST_FREE_LOAN => __('interest free loan'),
            self::OTHER => __('other'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::CURRENT => 'current',
            self::SAVINGS => 'savings',
            self::INTEREST_FREE_LOAN => 'interest_free_loan',
            self::OTHER => 'other',
        };
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
