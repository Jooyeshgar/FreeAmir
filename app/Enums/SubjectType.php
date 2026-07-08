<?php

namespace App\Enums;

use ValueError;

enum SubjectType: int
{
    case DEBTOR = 1;
    case CREDITOR = 2;
    case BOTH = 3;

    public function label(): string
    {
        return match ($this) {
            self::DEBTOR => __('Debtor'),
            self::CREDITOR => __('Creditor'),
            self::BOTH => __('Both'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::DEBTOR => 'debtor',
            self::CREDITOR => 'creditor',
            self::BOTH => 'both',
        };
    }

    public function isBoth(): bool
    {
        return $this === self::BOTH;
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
