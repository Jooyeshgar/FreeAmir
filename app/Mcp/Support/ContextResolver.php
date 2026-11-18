<?php

namespace App\Mcp\Support;

use App\Mcp\Auth\AuthContext;
use App\Models\FiscalYear;
use InvalidArgumentException;

class ContextResolver
{
    public const MONTH_NAMES = [
        1 => 'Farvardin',
        2 => 'Ordibehesht',
        3 => 'Khordad',
        4 => 'Tir',
        5 => 'Mordad',
        6 => 'Shahrivar',
        7 => 'Mehr',
        8 => 'Aban',
        9 => 'Azar',
        10 => 'Dey',
        11 => 'Bahman',
        12 => 'Esfand',
    ];

    protected static ?AuthContext $authContext = null;

    public static function authContext(): ?AuthContext
    {
        return self::$authContext;
    }

    public static function withAuthContext(?AuthContext $context, callable $callback): mixed
    {
        $previous = self::$authContext;
        self::$authContext = $context;
        try {
            return $callback();
        } finally {
            self::$authContext = $previous;
        }
    }

    /** Only the authenticated token owner's assigned fiscal years are eligible. */
    protected static function accessibleFiscalYears(): mixed
    {
        if (! self::$authContext) {
            throw new InvalidArgumentException(__('Access to fiscal year is not permitted.'));
        }

        return self::$authContext->user->fiscalYears();
    }

    public static function withContext(array $arguments, callable $callback): mixed
    {
        $previousFiscalYear = config('active-fiscal-year-id');
        $previousCompany = config('active-company-id');
        $previousFiscalYearNumber = config('active-company-fiscal-year');
        try {
            $fiscalYear = self::resolveFiscalYear($arguments);
            config([
                'active-fiscal-year-id' => (int) $fiscalYear->id,
                'active-company-id' => (int) $fiscalYear->company_id,
                'active-company-fiscal-year' => (int) $fiscalYear->year,
            ]);

            return $callback($fiscalYear);
        } finally {
            config([
                'active-fiscal-year-id' => $previousFiscalYear,
                'active-company-id' => $previousCompany,
                'active-company-fiscal-year' => $previousFiscalYearNumber,
            ]);
        }
    }

    protected static function positiveInteger(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException(__('Invalid ID or fiscal year.'));
        }

        return $id;
    }

    protected static function resolveFiscalYear(array $arguments = []): FiscalYear
    {
        $query = self::accessibleFiscalYears();
        $explicit = false;
        foreach (['fiscal_year_id' => 'fiscal_years.id', 'year' => 'fiscal_years.year'] as $key => $column) {
            if (array_key_exists($key, $arguments)) {
                $query->where($column, self::positiveInteger($arguments[$key]));
                $explicit = true;
            }
        }
        $years = $query->orderByDesc('fiscal_years.year')->orderByDesc('fiscal_years.id')->get();
        if ($years->isEmpty()) {
            throw new InvalidArgumentException(__('Requested fiscal year is not accessible or not found.'));
        }
        if (! isset($arguments['fiscal_year_id']) && $years->pluck('company_id')->unique()->count() > 1) {
            throw new InvalidArgumentException(__('Please specify a fiscal year ID to select a fiscal year.'));
        }
        if (! $explicit) {
            $preferred = config('active-fiscal-year-id');
            if ($preferred !== null) {
                $preferredYear = $years->firstWhere('id', self::positiveInteger($preferred));
                if ($preferredYear !== null) {
                    return $preferredYear;
                }
            }
        }

        return $years->first();
    }

    public static function getAvailableFiscalYears(?int $activeId = null): array
    {
        return self::accessibleFiscalYears()->with('company')->orderByDesc('fiscal_years.year')->get()->map(fn ($fy) => [
            'id' => (int) $fy->id,
            'company_id' => (int) $fy->company_id,
            'currency' => (string) ($fy->company?->currency ?: 'Rial'),
            'year' => (int) $fy->year,
            'title' => (string) ($fy->title ?? $fy->year),
            'is_active' => (int) $fy->id === $activeId,
        ])->values()->all();
    }

    /**
     * Get Jalali month inclusive Gregorian date boundaries (start YYYY-MM-DD, end YYYY-MM-DD).
     *
     * @return array{start: string, end: string, days: int, is_leap: bool}
     */
    public static function getJalaliMonthGregorianRange(int $jy, int $month): array
    {
        $days = getDaysInJalaliMonth($jy, $month);
        $startG = jalali_to_gregorian($jy, $month, 1);
        $endG = jalali_to_gregorian($jy, $month, $days);

        return [
            'start' => sprintf('%04d-%02d-%02d', $startG[0], $startG[1], $startG[2]),
            'end' => sprintf('%04d-%02d-%02d', $endG[0], $endG[1], $endG[2]),
            'days' => $days,
            'is_leap' => jcheckdate(12, 30, $jy),
        ];
    }

    /**
     * Resolve target period from input arguments (month, period, fiscal_year_id).
     *
     * @param  array<string, mixed>  $arguments
     * @return array{
     *     mode: 'month'|'fiscal_year',
     *     period: string,
     *     month: int|null,
     *     month_name: string|null,
     *     start_date: string|null,
     *     end_date: string|null,
     *     days_in_month: int|null,
     *     fiscal_year_id: int|null,
     *     fiscal_year_number: int,
     *     is_leap_year: bool
     * }
     */
    public static function resolvePeriod(array $arguments, FiscalYear $fiscalYear): array
    {
        $fiscalYearId = (int) $fiscalYear->id;
        $fiscalYearNumber = (int) $fiscalYear->year;
        $today = now();
        $todayJalali = gregorian_to_jalali($today->year, $today->month, $today->day);
        $currentMonth = $todayJalali[1];

        $explicitMonth = isset($arguments['month']) ? (int) $arguments['month'] : null;
        $periodArg = isset($arguments['period']) ? strtolower((string) $arguments['period']) : null;

        if ($explicitMonth === null && in_array($periodArg, ['current_month', 'previous_month'], true)) {
            $targetYear = $todayJalali[0] - ($periodArg === 'previous_month' && $currentMonth === 1 ? 1 : 0);
            if ($fiscalYearNumber !== $targetYear) {
                throw new InvalidArgumentException(__('The relative period is not in the selected fiscal year. Select the authorized fiscal year for that period or specify the month explicitly.'));
            }
        }

        if ($explicitMonth !== null) {
            if ($explicitMonth < 1 || $explicitMonth > 12) {
                throw new InvalidArgumentException(__('Jalali month must be between 1 and 12. Given: :month', ['month' => $explicitMonth]));
            }

            $range = self::getJalaliMonthGregorianRange($fiscalYearNumber, $explicitMonth);

            return [
                'mode' => 'month',
                'period' => 'specific_month',
                'month' => $explicitMonth,
                'month_name' => __(self::MONTH_NAMES[$explicitMonth] ?? 'Month :month', ['month' => $explicitMonth]),
                'start_date' => $range['start'],
                'end_date' => $range['end'],
                'days_in_month' => $range['days'],
                'fiscal_year_id' => $fiscalYearId,
                'fiscal_year_number' => $fiscalYearNumber,
                'is_leap_year' => $range['is_leap'],
            ];
        }

        if ($periodArg === 'current_month') {
            $range = self::getJalaliMonthGregorianRange($fiscalYearNumber, $currentMonth);

            return [
                'mode' => 'month',
                'period' => 'current_month',
                'month' => $currentMonth,
                'month_name' => __(self::MONTH_NAMES[$currentMonth] ?? 'Month :month', ['month' => $currentMonth]),
                'start_date' => $range['start'],
                'end_date' => $range['end'],
                'days_in_month' => $range['days'],
                'fiscal_year_id' => $fiscalYearId,
                'fiscal_year_number' => $fiscalYearNumber,
                'is_leap_year' => $range['is_leap'],
            ];
        }

        if ($periodArg === 'previous_month') {
            $prevMonth = $currentMonth > 1 ? $currentMonth - 1 : 12;
            $range = self::getJalaliMonthGregorianRange($fiscalYearNumber, $prevMonth);

            return [
                'mode' => 'month',
                'period' => 'previous_month',
                'month' => $prevMonth,
                'month_name' => __(self::MONTH_NAMES[$prevMonth] ?? 'Month :month', ['month' => $prevMonth]),
                'start_date' => $range['start'],
                'end_date' => $range['end'],
                'days_in_month' => $range['days'],
                'fiscal_year_id' => $fiscalYearId,
                'fiscal_year_number' => $fiscalYearNumber,
                'is_leap_year' => $range['is_leap'],
            ];
        }

        // Default: Full Fiscal Year
        $startG = jalali_to_gregorian($fiscalYearNumber, 1, 1);
        $lastDayEsfand = getDaysInJalaliMonth($fiscalYearNumber, 12);
        $endG = jalali_to_gregorian($fiscalYearNumber, 12, $lastDayEsfand);

        return [
            'mode' => 'fiscal_year',
            'period' => 'fiscal_year',
            'month' => null,
            'month_name' => null,
            'start_date' => sprintf('%04d-%02d-%02d', $startG[0], $startG[1], $startG[2]),
            'end_date' => sprintf('%04d-%02d-%02d', $endG[0], $endG[1], $endG[2]),
            'days_in_month' => null,
            'fiscal_year_id' => $fiscalYearId,
            'fiscal_year_number' => $fiscalYearNumber,
            'is_leap_year' => jcheckdate(12, 30, $fiscalYearNumber),
        ];
    }

    /**
     * Get rich context block including today's date, current Jalali month, and active fiscal year.
     *
     * @return array<string, mixed>
     */
    public static function getCurrentContext(FiscalYear $fiscalYear): array
    {
        $today = now();
        $todayJalali = gregorian_to_jalali($today->year, $today->month, $today->day);
        $fiscalYearId = (int) $fiscalYear->id;
        $fiscalYearNumber = (int) $fiscalYear->year;
        $fiscalYearTitle = (string) ($fiscalYear->title ?? $fiscalYear->year);
        $currency = (string) ($fiscalYear->company?->currency ?: 'Rial');

        $fyStart = jalali_to_gregorian($fiscalYearNumber, 1, 1);
        $fyEndDays = getDaysInJalaliMonth($fiscalYearNumber, 12);
        $fyEnd = jalali_to_gregorian($fiscalYearNumber, 12, $fyEndDays);

        return [
            'today_jalali' => sprintf('%04d/%02d/%02d', $todayJalali[0], $todayJalali[1], $todayJalali[2]),
            'today_gregorian' => $today->toDateString(),
            'current_jalali_year' => $todayJalali[0],
            'current_month_number' => $todayJalali[1],
            'current_month_name' => __(self::MONTH_NAMES[$todayJalali[1]] ?? 'Month :month', ['month' => $todayJalali[1]]),
            'current_jalali_day' => $todayJalali[2],
            'currency' => $currency,
            'active_fiscal_year' => [
                'id' => $fiscalYearId,
                'year' => $fiscalYearNumber,
                'title' => $fiscalYearTitle,
                'currency' => $currency,
                'is_current_calendar_year' => ($todayJalali[0] === $fiscalYearNumber),
                'is_leap_year' => jcheckdate(12, 30, $fiscalYearNumber),
                'start_date' => sprintf('%04d-%02d-%02d', $fyStart[0], $fyStart[1], $fyStart[2]),
                'end_date' => sprintf('%04d-%02d-%02d', $fyEnd[0], $fyEnd[1], $fyEnd[2]),
            ],
            'available_fiscal_years' => self::getAvailableFiscalYears($fiscalYearId),
        ];
    }
}
