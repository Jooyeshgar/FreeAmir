<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class ManagementPermissionFilter extends FilterAbstract
{
    protected array $searchables = [
        'name',
    ];
}
