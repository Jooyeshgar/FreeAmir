<?php

namespace App\Enums;

use ValueError;

enum EmployeeDutyStatus: int
{
    case LIABLE = 1;
    case COMPLETED = 2;
    case IN_PROGRESS = 3;
    case EXEMPT = 4;

    public function label(): string
    {
        return match ($this) {
            self::LIABLE => __('Liable'),
            self::COMPLETED => __('Completed'),
            self::IN_PROGRESS => __('In Progress'),
            self::EXEMPT => __('Exempt'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::LIABLE => 'liable',
            self::COMPLETED => 'completed',
            self::IN_PROGRESS => 'in_progress',
            self::EXEMPT => 'exempt',
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
