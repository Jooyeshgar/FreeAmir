<?php

namespace App\Mcp\Support;

use App\Mcp\Auth\AuthContext;
use App\Models\Config;
use App\Models\CustomerGroup;
use App\Models\ProductGroup;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class ContextResolver
{
    public const MONTH_NAMES = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    protected static ?AuthContext $authContext = null;

    private static function today(): Carbon
    {
        return Carbon::now(config('app.timezone', 'UTC'));
    }

    public static function authContext(): ?AuthContext
    {
        return self::$authContext;
    }

    public static function withAuthContext(?AuthContext $context, callable $callback): mixed
    {
        $previous = self::$authContext;
        $guard = auth()->guard();
        $previousUser = $guard->hasUser() ? $guard->user() : null;
        self::$authContext = $context;
        if ($context) {
            $guard->setUser($context->user);
        }
        try {
            return $callback();
        } finally {
            self::$authContext = $previous;
            if ($previousUser) {
                $guard->setUser($previousUser);
            } else {
                $guard->forgetUser();
            }
        }
    }

    public static function can(string $permission): bool
    {
        return self::$authContext?->can($permission) ?? false;
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
        $previousAmirConfig = config('amir');
        $previousActivityLogEnabled = config('activitylog.enabled');
        $previousAppConfig = config('app');
        $previousLocale = app()->getLocale();
        try {
            $fiscalYear = self::resolveFiscalYear($arguments);
            config([
                'active-fiscal-year-id' => (int) $fiscalYear->id,
                'active-company-id' => (int) $fiscalYear->company_id,
                'active-company-fiscal-year' => (int) $fiscalYear->year,
            ]);
            $settings = Config::withoutGlobalScopes()->whereNull('fiscal_year_id')->get()
                ->merge(Config::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYear->id)->get());
            config(['amir' => []]);
            foreach ($settings as $setting) {
                if ($setting->value !== null) {
                    self::applySetting($setting->key, $setting->value);
                }
            }

            return $callback();
        } finally {
            config([
                'active-fiscal-year-id' => $previousFiscalYear,
                'active-company-id' => $previousCompany,
                'active-company-fiscal-year' => $previousFiscalYearNumber,
                'amir' => $previousAmirConfig,
                'activitylog.enabled' => $previousActivityLogEnabled,
                'app' => $previousAppConfig,
            ]);
            app()->setLocale($previousLocale);
        }
    }

    private static function applySetting(string $key, mixed $value): void
    {
        config(['amir.'.$key => $value]);

        if ($key === 'app_activity_logger_enabled') {
            config(['activitylog.enabled' => str($value)->toBoolean()]);
        }

        if (str_starts_with($key, 'app_')) {
            $value = in_array($key, ['app_debug', 'app_registration', 'app_email_verification'], true)
                ? str($value)->toBoolean()
                : $value;
            config([str_replace('app_', 'app.', $key) => $value]);

            if ($key === 'app_locale' && in_array($value, ['fa', 'en'], true)) {
                app()->setLocale($value);
            }
        }

        if (! config('app.registration')) {
            config(['app.email_verification' => false]);
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

    protected static function resolveFiscalYear(array $arguments = []): mixed
    {
        $query = self::accessibleFiscalYears();
        $explicit = false;
        foreach (['fiscal_year_id' => 'fiscal_years.id', 'year' => 'fiscal_years.year', 'company_id' => 'fiscal_years.company_id'] as $key => $column) {
            if (array_key_exists($key, $arguments)) {
                $query->where($column, self::positiveInteger($arguments[$key]));
                $explicit = true;
            }
        }
        if (! $explicit) {
            $preferred = config('active-fiscal-year-id');
            if ($preferred !== null) {
                $preferredYear = (clone $query)->where('fiscal_years.id', self::positiveInteger($preferred))->first();
                if ($preferredYear !== null) {
                    return $preferredYear;
                }
            }
        }
        $years = $query->orderByDesc('fiscal_years.year')->orderByDesc('fiscal_years.id')->get();
        if ($years->isEmpty()) {
            throw new InvalidArgumentException(__('Requested fiscal year is not accessible or not found.'));
        }
        if (isset($arguments['year']) && ! isset($arguments['fiscal_year_id']) && ! isset($arguments['company_id']) && $years->pluck('company_id')->unique()->count() > 1) {
            throw new InvalidArgumentException(__('Please specify a fiscal year ID to select a fiscal year.'));
        }

        return $years->first();
    }

    public static function resolveFiscalYearId(array $arguments = []): ?int
    {
        return (int) self::resolveFiscalYear($arguments)->id;
    }

    public static function resolveFiscalYearNumber(?int $fiscalYearId = null, array $arguments = []): int
    {
        if ($fiscalYearId !== null) {
            $arguments['fiscal_year_id'] = $fiscalYearId;
        }

        return (int) self::resolveFiscalYear($arguments)->year;
    }

    public static function getAvailableFiscalYears(): array
    {
        $activeId = self::resolveFiscalYearId();

        return self::accessibleFiscalYears()->orderByDesc('fiscal_years.year')->get()->map(fn ($fy) => [
            'id' => (int) $fy->id,
            'company_id' => (int) $fy->company_id,
            'year' => (int) $fy->year,
            'title' => (string) ($fy->title ?? $fy->year),
            'is_active' => (int) $fy->id === $activeId,
        ])->values()->all();
    }

    public static function getAvailableYearsString(): string
    {
        return '['.implode(', ', array_map(fn ($fy) => $fy['year'].' (شناسه '.$fy['id'].')', self::getAvailableFiscalYears())).']';
    }

    public static function resolveCompanyId(array $arguments = []): ?int
    {
        return (int) self::resolveFiscalYear($arguments)->company_id;
    }

    /**
     * Check if a Jalali year is a leap year (کبیسه).
     */
    public static function isLeapJalaliYear(int $jy): bool
    {
        return jcheckdate(12, 30, $jy);
    }

    /**
     * Get number of days in a given Jalali month (1-12).
     */
    public static function getDaysInJalaliMonth(int $jy, int $month): int
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(__('Month number must be between 1 and 12. Given: :month', ['month' => $month]));
        }

        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return self::isLeapJalaliYear($jy) ? 30 : 29;
    }

    /**
     * Convert Jalali date to Gregorian date array: [year, month, day].
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        return jalali_to_gregorian($jy, $jm, $jd);
    }

    /**
     * Convert Gregorian date to Jalali date array: [year, month, day].
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        return gregorian_to_jalali($gy, $gm, $gd);
    }

    /**
     * Convert Gregorian date string (YYYY-MM-DD or datetime) to Jalali date string (YYYY/MM/DD).
     */
    public static function toJalaliDateString(?string $gregorianDate): ?string
    {
        if ($gregorianDate === null || trim($gregorianDate) === '') {
            return null;
        }

        $parts = explode(' ', trim($gregorianDate))[0];
        $date = explode('-', $parts);
        if (count($date) !== 3) {
            return null;
        }

        $j = self::gregorianToJalali((int) $date[0], (int) $date[1], (int) $date[2]);

        return sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
    }

    /**
     * Get Jalali month inclusive Gregorian date boundaries (start YYYY-MM-DD, end YYYY-MM-DD).
     *
     * @return array{start: string, end: string, days: int, is_leap: bool}
     */
    public static function getJalaliMonthGregorianRange(int $jy, int $month): array
    {
        $days = self::getDaysInJalaliMonth($jy, $month);
        $startG = self::jalaliToGregorian($jy, $month, 1);
        $endG = self::jalaliToGregorian($jy, $month, $days);

        return [
            'start' => sprintf('%04d-%02d-%02d', $startG[0], $startG[1], $startG[2]),
            'end' => sprintf('%04d-%02d-%02d', $endG[0], $endG[1], $endG[2]),
            'days' => $days,
            'is_leap' => self::isLeapJalaliYear($jy),
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
    public static function resolvePeriod(array $arguments = []): array
    {
        $fiscalYearId = self::resolveFiscalYearId($arguments);
        $fiscalYearNumber = self::resolveFiscalYearNumber($fiscalYearId, $arguments);
        $today = self::today();
        $todayJalali = self::gregorianToJalali($today->year, $today->month, $today->day);
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
                'month_name' => self::MONTH_NAMES[$explicitMonth] ?? "ماه {$explicitMonth}",
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
                'month_name' => self::MONTH_NAMES[$currentMonth] ?? "ماه {$currentMonth}",
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
                'month_name' => self::MONTH_NAMES[$prevMonth] ?? "ماه {$prevMonth}",
                'start_date' => $range['start'],
                'end_date' => $range['end'],
                'days_in_month' => $range['days'],
                'fiscal_year_id' => $fiscalYearId,
                'fiscal_year_number' => $fiscalYearNumber,
                'is_leap_year' => $range['is_leap'],
            ];
        }

        // Default: Full Fiscal Year
        $startG = self::jalaliToGregorian($fiscalYearNumber, 1, 1);
        $lastDayEsfand = self::getDaysInJalaliMonth($fiscalYearNumber, 12);
        $endG = self::jalaliToGregorian($fiscalYearNumber, 12, $lastDayEsfand);

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
            'is_leap_year' => self::isLeapJalaliYear($fiscalYearNumber),
        ];
    }

    /**
     * Get rich context block including today's date, current Jalali month, and active fiscal year.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function getCurrentContext(array $arguments = []): array
    {
        $today = self::today();
        $todayJalali = self::gregorianToJalali($today->year, $today->month, $today->day);
        $fiscalYearId = self::resolveFiscalYearId($arguments);
        $fiscalYearNumber = self::resolveFiscalYearNumber($fiscalYearId, $arguments);

        $fy = self::resolveFiscalYear($arguments);
        $fiscalYearTitle = (string) ($fy->title ?? $fy->year);

        $fyStart = self::jalaliToGregorian($fiscalYearNumber, 1, 1);
        $fyEndDays = self::getDaysInJalaliMonth($fiscalYearNumber, 12);
        $fyEnd = self::jalaliToGregorian($fiscalYearNumber, 12, $fyEndDays);

        return [
            'today_jalali' => sprintf('%04d/%02d/%02d', $todayJalali[0], $todayJalali[1], $todayJalali[2]),
            'today_gregorian' => $today->toDateString(),
            'current_jalali_year' => $todayJalali[0],
            'current_month_number' => $todayJalali[1],
            'current_month_name' => self::MONTH_NAMES[$todayJalali[1]] ?? "ماه {$todayJalali[1]}",
            'current_jalali_day' => $todayJalali[2],
            'active_fiscal_year' => [
                'id' => $fiscalYearId,
                'year' => $fiscalYearNumber,
                'title' => $fiscalYearTitle,
                'is_current_calendar_year' => ($todayJalali[0] === $fiscalYearNumber),
                'is_leap_year' => self::isLeapJalaliYear($fiscalYearNumber),
                'start_date' => sprintf('%04d-%02d-%02d', $fyStart[0], $fyStart[1], $fyStart[2]),
                'end_date' => sprintf('%04d-%02d-%02d', $fyEnd[0], $fyEnd[1], $fyEnd[2]),
            ],
            'available_fiscal_years' => self::getAvailableFiscalYears(),
            'customer_groups' => CustomerGroup::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)
                ->get(['id', 'name'])->map(fn ($g) => ['id' => (int) $g->id, 'name' => (string) $g->name])->values()->all(),
            'product_groups' => ProductGroup::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)
                ->get(['id', 'name'])->map(fn ($g) => ['id' => (int) $g->id, 'name' => (string) $g->name])->values()->all(),
            'warehouses' => Warehouse::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)
                ->get(['id', 'name', 'code'])->map(fn ($w) => ['id' => (int) $w->id, 'name' => (string) $w->name, 'code' => (string) $w->code])->values()->all(),
        ];
    }
}
