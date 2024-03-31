<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{

    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
    ];
}
