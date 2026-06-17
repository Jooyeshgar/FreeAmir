<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'amount',
        'date',
        'reference_number',
        'description',
        'payer_id',
        'document_id',
        'settlement_subject_id',
        'creator_id',
        'invoice_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function payer()
    {
        return $this->belongsTo(Customer::class, 'payer_id');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function settlementSubjectRelation()
    {
        return $this->belongsTo(Subject::class, 'settlement_subject_id');
    }

    public function settlementSubject(): ?Subject
    {
        if ($this->settlement_subject_id) {
            return $this->settlementSubjectRelation;
        }

        $customerSubjectId = (int) ($this->payer?->subject?->id ?? $this->payer?->subject_id);
        $transactions = $this->document?->transactions ?? collect();

        return $transactions->first(fn ($transaction) => (int) $transaction->subject_id !== $customerSubjectId)?->subject;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
