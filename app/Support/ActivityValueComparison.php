<?php

namespace App\Support;

/** Compare audit values without losing decimal precision through float casts. */
final class ActivityValueComparison
{
    public static function equivalent(mixed $old, mixed $new): bool
    {
        if ($old === $new) {
            return true;
        }

        if (is_numeric($old) && is_numeric($new)) {
            return self::normalizeNumber($old) === self::normalizeNumber($new);
        }

        return is_scalar($old) && is_scalar($new) && (string) $old === (string) $new;
    }

    private static function normalizeNumber(int|float|string $value): string
    {
        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', trim((string) $value), $parts)) {
            return (string) $value;
        }

        $fraction = $parts[3] ?? '';
        $digits = ltrim($parts[2].$fraction, '0');
        if ($digits === '') {
            return '0';
        }

        $significant = rtrim($digits, '0');
        $exponent = (int) ($parts[4] ?? 0) - strlen($fraction) + strlen($digits) - strlen($significant);

        return ($parts[1] === '-' ? '-' : '').$significant.'e'.$exponent;
    }
}
