<?php

namespace Tests\Unit;

use App\Support\ActivityValueComparison;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityValueComparisonTest extends TestCase
{
    #[DataProvider('values')]
    public function test_audit_value_equivalence(mixed $old, mixed $new, bool $equivalent): void
    {
        self::assertSame($equivalent, ActivityValueComparison::equivalent($old, $new));
        self::assertSame($equivalent, ActivityValueComparison::equivalent($new, $old));
    }

    public static function values(): array
    {
        return [
            'decimal and integer' => ['10.00', 10, true],
            'decimal strings' => ['10.00', '10', true],
            'decimal and float' => ['10.000', 10.0, true],
            'negative values' => ['-10.00', -10, true],
            'signed zero' => ['-0.00', 0, true],
            'fraction without integer' => ['.50', '0.5', true],
            'scientific notation' => ['1e3', '1000.00', true],
            'large precise values' => ['9007199254740993.00', '9007199254740993', true],
            'real decimal change' => ['10.00', '10.01', false],
            'precision retained' => ['9007199254740992', '9007199254740993', false],
            'fractional precision retained' => ['0.12345678901234567890', '0.12345678901234567891', false],
            'null is not zero' => [null, 0, false],
            'empty is not zero' => ['', 0, false],
            'boolean change retained' => [false, 0, false],
            'text change retained' => ['Before', 'After', false],
            'unchanged array' => [['a' => 10], ['a' => 10], true],
        ];
    }
}
