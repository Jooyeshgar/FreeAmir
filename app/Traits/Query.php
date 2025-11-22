<?php

namespace App\Traits;

use Closure;

trait Query
{
    public function baseQuery(array $relations = [], array $columns = ['*'])
    {
        return static::query()->select($columns)->with($relations);
    }

    public function getSome(
        array $relations = [],
        array $columns = ['*'],
        int $limit = 10,
        string $orderBy = 'name',
        string $direction = 'asc',
        array $options = []
    ) {
        $query = $this->baseQuery($relations, $columns);

        foreach ($options as $column => $value) {
            $query->where($column, $value);
        }

        return $query->orderBy($orderBy, $direction)
            ->limit($limit)
            ->get();
    }

    public function searchInModel(
        string $searchQuery = '',
        array $relations = [],
        array $columns = ['*'],
        int $limit = 10,
        string $orderBy = 'name',
        string $direction = 'asc',
        array $options = [],
        ?Closure $customQuery = null
    ) {
        $query = $this->baseQuery($relations, $columns);

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
            ->limit($limit)
            ->get();
    }
}
