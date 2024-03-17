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
}
