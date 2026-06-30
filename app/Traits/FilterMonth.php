<?php

namespace App\Traits;

trait FilterMonth
{
    public function month(int $month): void
    {
        if ($month > 0) {
            $this->builder->where('month', $month);
        }
    }
}
