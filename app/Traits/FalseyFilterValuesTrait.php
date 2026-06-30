<?php

namespace App\Traits;

trait FalseyFilterValuesTrait
{
    public function getFilters(): array
    {
        return array_filter($this->request->only($this->getFilterables()), fn ($value) => $value !== null);
    }
}
