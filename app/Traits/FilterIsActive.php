<?php

namespace App\Traits;

trait FilterIsActive
{
    public function is_active(bool $isActive): void
    {
        $this->builder->where('is_active', $isActive);
    }
}
