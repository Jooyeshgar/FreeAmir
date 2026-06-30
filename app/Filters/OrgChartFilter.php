<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class OrgChartFilter extends FilterAbstract
{
    protected array $searchables = [
        'title',
    ];
}
