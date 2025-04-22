<?php

namespace App\Helpers;

class NumberToWordHelper
{
    public static function convert($number)
    {

        $hyphen = '-';
        $conjunction = ' ' . __('and') . ' ';
        $separator = ', ';
        $negative = __('negative');
        $decimal = ' ' . __('point') . ' ';
        $dictionary = [
            0 => __('zero'),
            1 => __('one'),
            2 => __('two'),
            3 => __('three'),
            4 => __('four'),
            5 => __('five'),
            6 => __('six'),
            7 => __('seven'),
            8 => __('eight'),
            9 => __('nine'),
            10 => __('ten'),
            11 => __('eleven'),
            12 => __('twelve'),
            13 => __('thirteen'),
            14 => __('fourteen'),
            15 => __('fifteen'),
            16 => __('sixteen'),
            17 => __('seventeen'),
            18 => __('eighteen'),
            19 => __('nineteen'),
            20 => __('twenty'),
            30 => __('thirty'),
            40 => __('forty'),
            50 => __('fifty'),
            60 => __('sixty'),
            70 => __('seventy'),
            80 => __('eighty'),
            90 => __('ninety'),
            100 => __('hundred'),
            1000 => __('thousand'),
            1000000 => __('million'),
            1000000000 => __('billion'),
            1000000000000 => __('trillion'),
            1000000000000000 => __('quadrillion'),
            1000000000000000000 => __('quintillion'),
        ];

        if (! is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convert_number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );

            return false;
        }

        if ($number < 0) {
            return $negative . self::convert(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            [$number, $fraction] = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens = ((int) ($number / 10)) * 10;
                $units = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds = $number / 100;
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . self::convert($remainder);
                }
                break;
            default:
                $baseUnit = pow(1000, floor(log($number, 1000)));
                $numBaseUnits = (int) ($number / $baseUnit);
                $remainder = $number % $baseUnit;
                $string = self::convert($numBaseUnits) . ' ' . $dictionary[$baseUnit];
                if ($remainder) {
                    $string .= $remainder < 100 ? $conjunction : $separator;
                    $string .= self::convert($remainder);
                }
                break;
        }

        if ($fraction !== null && is_numeric($fraction)) {
            $string .= $decimal;
            $words = [];
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }
}
