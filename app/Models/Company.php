<?php

namespace App\Models;

use App\Models\Traits\QueryHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, QueryHelper;

    public $timestamps = false;

    protected $guarded = [];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
