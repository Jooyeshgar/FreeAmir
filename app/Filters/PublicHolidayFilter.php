<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterName;

class PublicHolidayFilter extends FilterAbstract
{
    use FilterName;

    protected array $filterables = [
        'name' => self::TYPE_STRING,
    ];

}
