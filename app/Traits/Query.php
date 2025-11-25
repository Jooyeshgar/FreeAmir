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
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
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
            $searchableFields = $this->searchableFields ?? ['name'];

            if (in_array('code', $this->fillable ?? [])) {
                $searchableFields[] = 'code';
            }

            $searchableFields = array_unique($searchableFields);

            $query->where(function ($q) use ($searchQuery, $searchableFields) {
                foreach ($searchableFields as $field) {
                    $q->orWhere($field, 'like', "%{$searchQuery}%");
                }
            });
        }

        foreach ($options as $column => $value) {
            $query->where($column, $value);
        }

        return $query->orderBy($orderBy, $direction)
            ->limit($limit);
    }

    public function getSearchableFields(): array
    {
        return $this->searchableFields ?? ['name'];
    }
}
