<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class ManagementRoleFilter extends FilterAbstract
{
    protected array $searchables = [
        'name',
    ];
}
