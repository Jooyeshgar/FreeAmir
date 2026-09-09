<?php

namespace App\Enums;

use Illuminate\Support\Facades\Lang;
use ValueError;

enum InvoiceType: int
{
    case BUY = 1;
    case SELL = 2;
    case RETURN_BUY = 3;
    case RETURN_SELL = 4;
    case VOID = 5;
    case BEGINNING_INVENTORY = 6;

    public function label(): string
    {
        return match ($this) {
            self::BUY => Lang::get('Buy'),
            self::SELL => Lang::get('Sell'),
            self::RETURN_BUY => Lang::get('Return from Buy'),
            self::RETURN_SELL => Lang::get('Return from Sell'),
            self::VOID => Lang::get('Void'),
            self::BEGINNING_INVENTORY => Lang::get('Beginning Inventory'),
        };
    }

    public function valueName(): string
    {
        return match ($this) {
            self::BUY => 'buy',
            self::SELL => 'sell',
            self::RETURN_BUY => 'return_buy',
            self::RETURN_SELL => 'return_sell',
            self::VOID => 'void',
            self::BEGINNING_INVENTORY => 'beginning_inventory',
        };
    }

    public function isSell(): bool
    {
        return in_array($this, [self::SELL, self::RETURN_SELL]);
    }

    public function isVoid(): bool
    {
        return $this === self::VOID;
    }

    public function isBuy(): bool
    {
        return in_array($this, [self::BUY, self::RETURN_BUY, self::BEGINNING_INVENTORY]);
    }

    public function isReturn(): bool
    {
        return in_array($this, [self::RETURN_BUY, self::RETURN_SELL]);
    }

    public function isBeginningInventory(): bool
    {
        return $this === self::BEGINNING_INVENTORY;
    }

    public static function options(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->valueName()] = $case->label();

            return $carry;
        }, []);
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
