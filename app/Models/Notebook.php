<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notebook extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'subject_id',
        'bill_id',
        'user_id',
        'description',
        'value',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

}
