<?php

namespace App\Traits;

trait FilterEmployeeId
{
    public function employee_id(int $employeeId): void
    {
        if ($employeeId > 0) {
            $this->builder->where('employee_id', $employeeId);
        }
    }
}
