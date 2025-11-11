<?php

namespace App\Models;

use App\Enums\InvoiceType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'number',
        'code',
        'date',
        'document_id',
        'customer_id',
        'creator_id',
        'subtraction',
        'tax',
        'ship_date',
        'ship_via',
        'description',
        'invoice_type',
        'active',
        'vat',
        'amount',
    ];

    protected $casts = [
        'invoice_type' => InvoiceType::class,
        'date' => 'date',
        'ship_date' => 'date',
        'active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
        static::creating(function ($model) {
            $model->company_id = session('active-company-id');
        });
    }

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function ancillaryCosts()
    {
        return $this->hasMany(AncillaryCost::class, 'invoice_id');
    }
}
