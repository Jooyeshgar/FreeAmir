<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class WorkSiteFilter extends FilterAbstract
{
    protected array $searchables = [
        'name',
        'code',
    ];
}
