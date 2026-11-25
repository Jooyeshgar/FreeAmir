<?php

namespace App\Services;

use Carbon\Carbon;

class ChartCalendar
{
    /** @return array<string, string> Gregorian month labels keyed by YYYY-MM. */
    public static function gregorianMonths(Carbon $from, Carbon $to): array
    {
        $months = [];

        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonth()) {
            $months[$month->format('Y-m')] = $month->format('M Y');
        }

        return $months;
    }
}
