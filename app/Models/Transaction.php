<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'subject_id',
        'document_id',
        'user_id',
        'description',
        'value',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

}
