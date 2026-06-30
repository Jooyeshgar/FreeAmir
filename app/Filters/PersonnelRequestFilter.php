<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterEmployeeId;
use App\Traits\FilterStatus;

class PersonnelRequestFilter extends FilterAbstract
{
    use FilterEmployeeId;
    use FilterStatus;

    protected array $filterables = [
        'employee_id' => self::TYPE_INTEGER,
        'status' => self::TYPE_STRING,
    ];

}
