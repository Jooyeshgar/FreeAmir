<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelNestedSet\HasNestedSets;

class Subject extends Model
{
    use HasNestedSets;

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'type',
    ];

    // Define other methods as needed
}
