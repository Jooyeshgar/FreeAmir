<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FalseyFilterValuesTrait;
use App\Traits\FilterEmployeeId;
use Carbon\Carbon;

class AttendanceLogFilter extends FilterAbstract
{
    use FalseyFilterValuesTrait;
    use FilterEmployeeId;

    protected array $filterables = [
        'employee_id' => self::TYPE_INTEGER,
        'date_from' => self::TYPE_STRING,
        'date_to' => self::TYPE_STRING,
        'is_manual' => self::TYPE_BOOLEAN,
    ];

    public function date_from(string $date): void
    {
        if ($date !== '') {
            $this->builder->whereDate('log_date', '>=', Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($date))->format('Y-m-d'));
        }
    }

    public function date_to(string $date): void
    {
        if ($date !== '') {
            $this->builder->whereDate('log_date', '<=', Carbon::createFromFormat('Y/m/d', jalali_to_gregorian_date($date))->format('Y-m-d'));
        }
    }

    public function is_manual(bool $isManual): void
    {
        $this->builder->where('is_manual', $isManual);
    }
}
