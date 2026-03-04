<?php

namespace App\Enums;

enum AttendanceImportType: string
{
    /**
     * Tab-separated file format:
     * col1 = device_id  col2 = datetime  col3 = ignored  col4 = log_type (0=in, 1=out)  col5 = ignored  col6 = ignored
     */
    case DeviceTsv = 'device_tsv';

    public function label(): string
    {
        return match ($this) {
            self::DeviceTsv => __('Device TSV (tab-separated)'),
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn ($case) => ['value' => $case->value, 'label' => $case->label()], self::cases()),
            'label',
            'value'
        );
    }
}
