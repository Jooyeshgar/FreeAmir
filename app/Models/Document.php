<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{

    public $timestamps = true;

    protected $fillable = [
        'number',
        'date',
        'permanent',
    ];

    public function Transaction() {
        return $this->hasMany(Transaction::class);
    }
}
