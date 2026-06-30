<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FilterEmployeeId;
use App\Traits\FilterMonth;
use App\Traits\FilterStatus;
use Illuminate\Database\Eloquent\Builder;

class PayrollFilter extends FilterAbstract
{
    use FilterEmployeeId;
    use FilterMonth;
    use FilterStatus;

    protected array $filterables = [
        'employee_id' => self::TYPE_INTEGER,
        'month' => self::TYPE_INTEGER,
        'organization_unit_id' => self::TYPE_INTEGER,
        'status' => self::TYPE_STRING,
    ];

    public function organization_unit_id(int $organizationUnitId): void
    {
        if ($organizationUnitId > 0) {
            $this->builder->whereHas('employee', fn (Builder $employeeQuery) => $employeeQuery->where('organization_unit_id', $organizationUnitId));
        }
    }

}
