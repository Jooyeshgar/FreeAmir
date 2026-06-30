<?php

namespace App\Traits;

trait FilterGroupName
{
    public function group_name(string $groupName): void
    {
        if ($groupName !== '') {
            $this->builder->whereHas($this->groupNameRelation, fn ($query) => $query->where('name', 'like', '%'.$groupName.'%'));
        }
    }
}
