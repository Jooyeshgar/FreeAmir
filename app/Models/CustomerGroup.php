<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{

    public $timestamps = true; 

    protected $fillable = [
        'code',
        'name',
        'desc',
    ];
}
