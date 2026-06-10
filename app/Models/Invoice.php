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
        'company_id',
        'taxID',
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

    public function moadianHistories()
    {
        return $this->hasMany(MoadianHistory::class, 'invoice_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function paidAmount(): float
    {
        return (float) $this->payments->whereNotNull('document_id')->sum('amount');
    }

    public function remainingAmount(): float
    {
        return max((float) $this->amount - $this->paidAmount(), 0.0);
    }

    public function paymentStatus(): string
    {
        $paid = $this->paidAmount();

        return match (true) {
            $paid <= 0 => 'unpaid',
            $paid < (float) $this->amount => 'partially_paid',
            default => 'paid',
        };
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->paymentStatus()) {
            'unpaid' => __('Unpaid'),
            'partially_paid' => __('Partially paid'),
            default => __('Paid'),
        };
    }

    public function latestMoadianHistory()
    {
        return $this->hasOne(MoadianHistory::class, 'invoice_id')->latestOfMany();
    }

    public function returnedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'returned_invoice_id');
    }

    /**
     * Return invoice for the current returned invoice (If it is returned). e.g. sell -> return sell
     */
    public function getReturnInvoice()
    {
        return Invoice::where('returned_invoice_id', $this->id)->whereNot('invoice_type', InvoiceType::VOID)->get();
    }

    /**
     *  Returned invoice for the current return invoice. e.g. return sell -> sell
     */
    public function getReturnedInvoice()
    {
        return Invoice::whereNot('invoice_type', InvoiceType::VOID)->find($this->returned_invoice_id);
    }

    /**
     * Void invoice for the current voided invoice (If it is voided). e.g. sell -> void
     */
    public function voidInvoice()
    {
        return $this->hasOne(Invoice::class, 'returned_invoice_id')->where('invoice_type', InvoiceType::VOID);
    }

    /**
     *  The original invoice that was voided by this void invoice (if any). Example: void -> sell
     */
    public function voidedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'returned_invoice_id')->where('invoice_type', '!=', InvoiceType::VOID);
    }
}
