<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterEmployeeId;
use App\Traits\FilterMonth;

class MonthlyAttendanceFilter extends FilterAbstract
{
    use FilterEmployeeId;
    use FilterMonth;

    protected array $filterables = [
        'employee_id' => self::TYPE_INTEGER,
        'month' => self::TYPE_INTEGER,
    ];

}
