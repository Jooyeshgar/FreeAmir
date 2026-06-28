<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FalseyFilterValuesTrait;
use App\Traits\FilterIsActive;

class ApiEmployeeFilter extends FilterAbstract
{
    use FalseyFilterValuesTrait;
    use FilterIsActive;

    protected array $filterables = [
        'is_active' => self::TYPE_BOOLEAN,
    ];
}
