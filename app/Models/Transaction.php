<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id',
        'document_id',
        'user_id',
        'desc',
        'value',
    ];

    /**
     * Get the subject associated with the transaction.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the document associated with the transaction.
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user associated with the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
