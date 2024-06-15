<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\App;

/**
 * Format a number with separators, parentheses for negatives,
 * and optionally convert to Farsi if the locale is Persian.
 *
 * @param float|int $number
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

function formatDate(Carbon $date)
{
	$locale = App::getLocale();
	if ($locale === 'fa' || $locale === 'fa_IR') {
		return convertToFarsi(gregorian_to_jalali_date($date ?? now()));
	}

	return $date->format('Y-m-d');
}

/**
 * Convert a number string to Farsi digits.
 *
 * @param string $number
 * @return string
 */
function convertToFarsi($number)
{
	$farsiDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '/'];
	$englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '-'];

	return str_replace($englishDigits, $farsiDigits, $number);
}
