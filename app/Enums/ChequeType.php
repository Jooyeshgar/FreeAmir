<?php

namespace App\Enums;

use ValueError;

enum ChequeType: int
{
    // Lifecycle statuses retain the legacy numeric values where possible.
    case REGISTERED = 1;
    case DEPOSITED = 2;
    case CLEARED = 3;
    case BOUNCED = 4;
    case RETURNED = 5;
    case ENDORSED = 6;
    case ISSUED = 7;
    case CANCELLED = 8;
    case GUARANTEE_RECEIVED = 9;
    case GUARANTEE_GIVEN = 10;

    // Direction and purpose use separate numeric ranges.
    case RECEIVABLE = 101;
    case PAYABLE = 102;
    case SETTLEMENT = 201;
    case GUARANTEE = 202;

    public function label(): string
    {
        return match ($this) {
            self::RECEIVABLE, self::PAYABLE => __('cheques direction '.$this->valueName()),
            self::SETTLEMENT, self::GUARANTEE => __('cheques purpose '.$this->valueName()),
            default => __('cheques status '.$this->valueName()),
        };
    }

    public function valueName(): string
    {
        return strtolower($this->name);
    }

    public function color(): string
    {
        return match ($this) {
            self::CLEARED => 'success',
            self::BOUNCED => 'error',
            self::DEPOSITED => 'info',
            self::ENDORSED => 'secondary',
            self::RETURNED, self::CANCELLED => 'neutral',
            self::GUARANTEE_RECEIVED, self::GUARANTEE_GIVEN => 'warning',
            default => 'primary',
        };
    }

    public static function directions(): array
    {
        return [self::RECEIVABLE, self::PAYABLE];
    }

    public static function purposes(): array
    {
        return [self::SETTLEMENT, self::GUARANTEE];
    }

    public static function statuses(): array
    {
        return [
            self::REGISTERED,
            self::DEPOSITED,
            self::CLEARED,
            self::ENDORSED,
            self::BOUNCED,
            self::RETURNED,
            self::ISSUED,
            self::CANCELLED,
            self::GUARANTEE_RECEIVED,
            self::GUARANTEE_GIVEN,
        ];
    }

    public static function directionValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::directions());
    }

    public static function purposeValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::purposes());
    }

    public static function statusValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::statuses());
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
            if ($case->valueName() === strtolower((string) $value) || $case->name === strtoupper((string) $value)) {
                return $case;
            }
        }

        return null;
    }
}
