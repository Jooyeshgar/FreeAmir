<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;

class WorkShiftFilter extends FilterAbstract
{
    protected array $searchables = [
        'name',
    ];
}
