<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;

class Subject extends Model
{
    use NodeTrait;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'type',
    ];

    protected $attributes = [
        'parent_id' => 0,
    ];

    /**
     * Scope a query to only include first level children of root node.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFirstLevel($query)
    {
        return $query->where('parent_id', 0);
    }
}
