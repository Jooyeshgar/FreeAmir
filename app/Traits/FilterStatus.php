<?php

namespace App\Traits;

trait FilterStatus
{
    public function status(string $status): void
    {
        if ($status !== '') {
            $this->builder->where('status', $status);
        }
    }
}
