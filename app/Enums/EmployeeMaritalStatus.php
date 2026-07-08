<?php

namespace App\Enums;

use ValueError;

enum EmployeeMaritalStatus: int
{
    case SINGLE = 1;
    case MARRIED = 2;
    case DIVORCED = 3;
    case WIDOWED = 4;

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => __('Single'),
            self::MARRIED => __('Married'),
            self::DIVORCED => __('Divorced'),
            self::WIDOWED => __('Widowed'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::SINGLE => 'single',
            self::MARRIED => 'married',
            self::DIVORCED => 'divorced',
            self::WIDOWED => 'widowed',
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn ($case) => ['value' => $case->valueName(), 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
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
