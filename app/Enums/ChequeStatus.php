<?php

namespace App\Enums;

use ValueError;

enum ChequeStatus: int
{
    case STATUS_1 = 1;
    case STATUS_2 = 2;
    case STATUS_3 = 3;
    case STATUS_4 = 4;
    case STATUS_5 = 5;

    public function label(): string
    {
        return __($this->valueName());
    }

    public function valueName(): string
    {
        return (string) $this->value;
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
