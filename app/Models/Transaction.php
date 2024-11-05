<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

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
    public static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            $model->subject->fixTree($model);
            return true;
        });
    }
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

    public function getDebitAttribute()
    {
        return $this->value < 0 ? formatNumber(-1 * $this->value) : '';
    }

    public function getCreditAttribute()
    {
        return $this->value > 0 ? formatNumber($this->value) : '';
    }
}
