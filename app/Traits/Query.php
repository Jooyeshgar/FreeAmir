<?php

namespace App\Traits;

use Closure;
use Illuminate\Database\Eloquent\Builder;

trait Query
{
    public function scopeSome(
        Builder $query,
        int $limit = 10,
        string $orderBy = 'name',
        string $direction = 'asc',
        array $options = []
    ): Builder {
        foreach ($options as $column => $value) {
            $query->where($column, $value);
        }

        return $query->orderBy($orderBy, $direction)
            ->limit($limit);
    }

    public function scopeSearchInModel(
        Builder $query,
        string $searchQuery = '',
        int $limit = 10,
        string $orderBy = 'name',
        string $direction = 'asc',
        array $options = [],
        ?Closure $customQuery = null
    ): Builder {

        if ($customQuery) {
            $query = $customQuery($query);
        }

        if ($searchQuery) {
            $query->where('name', 'like', "%{$searchQuery}%");
        }

        foreach ($options as $column => $value) {
            $query->where($column, $value);
        }

        return $query->orderBy($orderBy, $direction)
            ->limit($limit);
    }
}
