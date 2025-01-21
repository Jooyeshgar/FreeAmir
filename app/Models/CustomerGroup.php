<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subject_id',
        'name',
        'description',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
