<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    public $timestamps = true;
    protected $casts = [
        'date'=>'date'
    ];
    protected $fillable = [
        'number',
        'date',
        'title',
        'permanent',
        'user_id',
    ];

    public function Transaction()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getJalaliDateAttribute()
    {
        return gregorian_to_jalali_date($this->date??now());
    }
}
