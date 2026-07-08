<?php

namespace App\Enums;

use ValueError;

enum ThursdayStatus: int
{
    case HOLIDAY = 1;
    case FULL_DAY = 2;
    case HALF_DAY = 3;

    public function label(): string
    {
        return match ($this) {
            self::HOLIDAY => __('Holiday'),
            self::FULL_DAY => __('Full Day'),
            self::HALF_DAY => __('Half Day'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::HOLIDAY => 'holiday',
            self::FULL_DAY => 'full_day',
            self::HALF_DAY => 'half_day',
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
