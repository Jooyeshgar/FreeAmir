<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterEmployeeId;

class SalaryDecreeFilter extends FilterAbstract
{
    use FilterEmployeeId;

    protected array $filterables = [
        'employee_id' => self::TYPE_INTEGER,
    ];

    protected array $searchables = [
        'name',
    ];

}
