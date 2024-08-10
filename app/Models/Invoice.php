<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'code',
        'date',
        'document_id',
        'customer_id',
        'addition',
        'subtraction',
        'tax',
        'cash_payment',
        'ship_date',
        'ship_via',
        'permanent',
        'description',
        'is_sell',
        'active',
        'vat',
        'amount',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
