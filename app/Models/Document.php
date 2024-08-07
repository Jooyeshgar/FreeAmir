<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    public $timestamps = true;
    protected $casts = [
        'date' => 'date'
    ];
    protected $fillable = [
        'number',
        'date',
        'title',
        'permanent',
        'user_id',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function getFormattedDateAttribute()
    {
        return formatDate($this->date ?? now());
    }
}
