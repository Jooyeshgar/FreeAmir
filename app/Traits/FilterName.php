<?php

namespace App\Traits;

trait FilterName
{
    public function name(string $name): void
    {
        if ($name !== '') {
            $this->builder->where('name', 'like', '%'.$name.'%');
        }
    }
}
