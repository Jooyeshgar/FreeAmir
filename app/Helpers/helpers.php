<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

/**
 * Format a number with separators, parentheses for negatives,
 * and optionally convert to Farsi if the locale is Persian.
 *
 * @param  float|int  $number
 * @return string
 */
function formatNumber($number)
{
    $locale = App::getLocale();
    $isPersian = $locale === 'fa' || $locale === 'fa_IR';

    $formattedNumber = number_format(abs($number));

    if ($isPersian) {
        $formattedNumber = convertToFarsi($formattedNumber);
    }

    if ($number < 0) {
        $formattedNumber = "($formattedNumber)";
    }

    return $formattedNumber;
}

function formatDocumentNumber(float $number)
{
    if (floor($number) == $number) {
        return formatNumber(intval($number));
    }

    $documentNumber = in_array(App::getLocale(), ['fa', 'fa_IR']) ?
        convertToFarsi(number_format($number, 2, '/')) :
            number_format($number, 2, '');

    return $documentNumber;
}

function formatDate(Carbon|string|null $date)
{
    if (is_null($date)) {
        return '';
    }
    if (is_string($date)) {
        $date = Carbon::createFromFormat('Y-m-d', $date);
    }

    $locale = App::getLocale();
    if ($locale === 'fa' || $locale === 'fa_IR') {
        return convertToFarsi(gregorian_to_jalali_date($date ?? now()));
    }

    return $date->format('Y-m-d');
}

function formatMinimalDate(?Carbon $date)
{
    if (is_null($date)) {
        return '';
    }

    $locale = App::getLocale();
    if ($locale === 'fa' || $locale === 'fa_IR') {
        return jdate('m/d', $date->timestamp ?? now());
    }

    return $date->format('m-d');
}

function formatCode(string|null $code)
{
    if (is_null($code)) {
        return '';
    }

    $chunks = str_split($code, 3);

    $code = implode('/', $chunks);

    if (in_array(App::getLocale(), ['fa', 'fa_IR'])) {
        $code = convertToFarsi($code);
    }

    return $code;
}

/**
 * Convert a number string to Farsi digits.
 *
 * @param  string  $number
 * @return string
 */
function convertToFarsi($number)
{
    $farsiDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    return str_replace($englishDigits, $farsiDigits, $number);
}

/**
 * Convert a string number from Persian or English to a float.
 *
 * @param  mixed  $number
 * @return float
 */
function convertToFloat($number)
{
    $farsiDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', ','];
    $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ''];

    $convertedNumber = str_replace($farsiDigits, $englishDigits, $number);

    $cleanedNumber = preg_replace('/[^0-9\.-]/', '', $convertedNumber);

    $cleanedNumber = preg_replace('/\.(?=.*\.)/', '', $cleanedNumber);

    return floatval($cleanedNumber);
}

/**
 * Convert a string number from Persian or English to a int.
 *
 * @param  mixed  $number
 * @return float
 */
function convertToInt($number)
{
    $farsiDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', ','];
    $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ''];

    $convertedNumber = str_replace($farsiDigits, $englishDigits, $number);

    $cleanedNumber = preg_replace('/[^0-9\.-]/', '', $convertedNumber);

    $cleanedNumber = preg_replace('/\.(?=.*\.)/', '', $cleanedNumber);

    return intval($cleanedNumber);
}

/**
 * Convert a date from Jalali to Gregorian based on locale.
 *
 * @param  string  $date  Date in 'YYYY/MM/DD' or 'YYYY-MM-DD' format
 * @return string Converted date in Gregorian format (if locale is Persian), otherwise original date
 */
function convertToGregorian($date)
{
    $locale = App::getLocale();

    if ($locale === 'fa' || $locale === 'fa_IR') {
        return jalali_to_gregorian_date($date);
    }

    return $date;
}

/**
 * Convert a date from Gregorian to Jalali based on locale.
 *
 * Accepts a Carbon instance or a date string in 'YYYY-MM-DD' or 'YYYY/MM/DD'.
 * When locale is Persian, returns a Jalali date string; otherwise returns the original
 * date (Carbon formatted as 'Y-m-d' if needed).
 *
 * @param  \Carbon\Carbon|string|null  $date
 * @return string
 */
function convertToJalali($date)
{
    $locale = App::getLocale();

    if ($locale === 'fa' || $locale === 'fa_IR') {
        return gregorian_to_jalali_date($date);
    }

    if ($date instanceof Carbon) {
        return $date->format('Y-m-d');
    }

    return (string) $date;
}

if (! function_exists('model_route')) {
    /**
     * Convert a model class or instance to a route name.
     *
     * @param  string|object  $model  The model class name or instance
     * @param  string  $action  The action for the route (default: 'index')
     * @param  bool  $plural  Whether to pluralize the route name (default: true)
     * @return string The route name
     */
    function model_route($model, string $action = 'index', bool $plural = true): string
    {
        $routeName = is_object($model) ? class_basename($model) : $model;
        $routeName = Str::snake($routeName, '-');

        if ($plural) {
            $routeName = Str::plural($routeName);
        }

        return $routeName.'.'.$action;
    }
}
