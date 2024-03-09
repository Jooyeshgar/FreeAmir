<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    public $incrementing = false; 

    public $timestamps = false;
    protected $fillable = [
        'key',
        'value',
        'desc',
        'type',
        'category',
    ];
}
