<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class JalaliMonthDaysTest extends TestCase
{
    #[TestWith([1404, 1, 31])]
    #[TestWith([1404, 6, 31])]
    #[TestWith([1404, 7, 30])]
    #[TestWith([1404, 11, 30])]
    #[TestWith([1403, 12, 30])]
    #[TestWith([1404, 12, 29])]
    public function test_returns_days_in_jalali_month(int $year, int $month, int $expected): void
    {
        self::assertSame($expected, getDaysInJalaliMonth($year, $month));
    }

    #[TestWith([0])]
    #[TestWith([13])]
    public function test_rejects_invalid_month(int $month): void
    {
        $this->expectException(InvalidArgumentException::class);

        getDaysInJalaliMonth(1404, $month);
    }
}
