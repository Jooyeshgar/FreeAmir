<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterEmployeeId;

class ApiAttendanceLogFilter extends FilterAbstract
{
    use FilterEmployeeId;

    protected array $filterables = [
        'employee_id' => self::TYPE_INTEGER,
        'date_from' => self::TYPE_STRING,
        'date_to' => self::TYPE_STRING,
    ];

    public function date_from(string $date): void
    {
        $this->builder->whereDate('log_date', '>=', $date);
    }

    public function date_to(string $date): void
    {
        $this->builder->whereDate('log_date', '<=', $date);
    }
}
