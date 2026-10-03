<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    public function fiscalYears()
    {
        return $this->hasMany(FiscalYear::class);
    }
}
