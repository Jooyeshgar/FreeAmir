<?php

namespace App\Enums;

use ValueError;

enum EmployeeEducationLevel: int
{
    case BELOW_DIPLOMA = 1;
    case DIPLOMA = 2;
    case ASSOCIATE = 3;
    case BACHELOR = 4;
    case MASTER = 5;
    case PHD = 6;

    public function label(): string
    {
        return match ($this) {
            self::BELOW_DIPLOMA => __('Below Diploma'),
            self::DIPLOMA => __('Diploma'),
            self::ASSOCIATE => __('Associate'),
            self::BACHELOR => __('Bachelor'),
            self::MASTER => __('Master'),
            self::PHD => __('PhD'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::BELOW_DIPLOMA => 'below_diploma',
            self::DIPLOMA => 'diploma',
            self::ASSOCIATE => 'associate',
            self::BACHELOR => 'bachelor',
            self::MASTER => 'master',
            self::PHD => 'phd',
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
