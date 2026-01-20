<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'content',
        'rating',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function commentBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
