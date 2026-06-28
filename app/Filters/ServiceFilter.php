<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterGroupName;
use App\Traits\FilterName;

class ServiceFilter extends FilterAbstract
{
    use FilterGroupName;
    use FilterName;

    protected string $groupNameRelation = 'serviceGroup';

    protected array $filterables = [
        'name' => self::TYPE_STRING,
        'group_name' => self::TYPE_STRING,
    ];

}
