<?php

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum InvoiceType: string
{
    case BUY = 'buy';
    case SELL = 'sell';
    case RETURN_BUY = 'return_buy';
    case RETURN_SELL = 'return_sell';

    /**
     * Get translated label for the invoice type.
     *
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::BUY => Lang::get('Buy'),
            self::SELL => Lang::get('Sell'),
            self::RETURN_BUY => Lang::get('Return from Buy'),
            self::RETURN_SELL => Lang::get('Return from Sell'),
        };
    }

    /**
     * Check if this is a sell type (sell or return from sell).
     *
     * @return bool
     */
    public function isSell(): bool
    {
        return in_array($this, [self::SELL, self::RETURN_SELL]);
    }

    /**
     * Check if this is a buy type (buy or return from buy).
     *
     * @return bool
     */
    public function isBuy(): bool
    {
        return in_array($this, [self::BUY, self::RETURN_BUY]);
    }

    /**
     * Check if this is a return type (return from buy or return from sell).
     *
     * @return bool
     */
    public function isReturn(): bool
    {
        return in_array($this, [self::RETURN_BUY, self::RETURN_SELL]);
    }

    /**
     * Get all invoice types as an associative array for dropdowns.
     *
     * @return array
     */
    public static function options(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = $case->label();
            return $carry;
        }, []);
    }
}
