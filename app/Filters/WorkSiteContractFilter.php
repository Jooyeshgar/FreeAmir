<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class WorkSiteContractFilter extends FilterAbstract
{
    protected array $searchables = [
        'name',
        'code',
    ];
}
