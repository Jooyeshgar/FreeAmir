<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

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
        'status',
        'active',
        'vat',
        'amount',
        'title',
        'returned_invoice_id',
    ];

    protected $casts = [
        'invoice_type' => InvoiceType::class,
        'status' => InvoiceStatus::class,
        'date' => 'date',
        'ship_date' => 'date',
        'active' => 'boolean',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
        static::creating(function ($model) {
            $model->company_id = getActiveCompany();
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

    public function returnedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'returned_invoice_id');
    }

    // Return invoice for the current returned invoice (If it is returned). e.g. sell -> return sell
    public function getReturnInvoice()
    {
        return Invoice::where('returned_invoice_id', $this->id)->first();
    }

    // Returned invoice for the current return invoice. e.g. return sell -> sell
    public function getReturnedInvoice()
    {
        return Invoice::find($this->returned_invoice_id);
    }
}
