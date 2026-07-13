<?php

namespace App\Enums;

use ValueError;

enum AncillaryCostType: int
{
    case Shipping = 1;
    case Insurance = 2;
    case Customs = 3;
    case Taxes = 4;
    case Loading = 5;
    case Other = 6;

    public function label(): string
    {
        return match ($this) {
            self::Shipping => __('Transportation Costs'),
            self::Insurance => __('Shipping Insurance'),
            self::Customs => __('Customs Fees And Import Duties'),
            self::Taxes => __('Non Refundable Taxes'),
            self::Loading => __('Loading And Unloading Costs'),
            self::Other => __('Other Costs'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::Shipping => 'Shipping',
            self::Insurance => 'Insurance',
            self::Customs => 'Customs',
            self::Taxes => 'Taxes',
            self::Loading => 'Loading',
            self::Other => 'Other',
        };
    }

    public static function labels(): array
    {
        return array_map(
            fn (self $case) => $case->label(),
            self::cases()
        );
    }

    public static function options(): array
    {
        return array_combine(
            self::valueNames(),
            self::labels()
        );
    }

    public static function values(): array
    {
        return self::valueNames();
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
