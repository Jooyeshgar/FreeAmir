<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
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
        'company_id',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($model) {
            $model->company_id ??= session('active-company-id');
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function commentBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
