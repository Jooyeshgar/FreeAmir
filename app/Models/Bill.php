<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{

    public $timestamps = true;

    protected $fillable = [
        'number',
        'date',
        'permanent',
    ];

    public function notebook() {
        return $this->hasMany(Notebook::class);
    }
}
