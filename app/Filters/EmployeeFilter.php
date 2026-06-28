<?php

namespace App\Filters;

use AliMousavi\Filoquent\Filters\FilterAbstract;
use App\Traits\FalseyFilterValuesTrait;
use App\Traits\FilterIsActive;

class EmployeeFilter extends FilterAbstract
{
    use FalseyFilterValuesTrait;
    use FilterIsActive;

    protected array $filterables = [
        'is_active' => self::TYPE_BOOLEAN,
        'work_site_id' => self::TYPE_INTEGER,
        'contract_framework_id' => self::TYPE_INTEGER,
    ];

    protected array $searchables = [
        'first_name',
        'last_name',
        'code',
        'national_code',
    ];

    public function work_site_id(int $workSiteId): void
    {
        if ($workSiteId > 0) {
            $this->builder->where('work_site_id', $workSiteId);
        }
    }

    public function contract_framework_id(int $contractFrameworkId): void
    {
        if ($contractFrameworkId > 0) {
            $this->builder->where('contract_framework_id', $contractFrameworkId);
        }
    }
}
