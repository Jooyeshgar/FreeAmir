<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'number',
        'type',
        'owner',
        'bank_id',  // Foreign key to the Bank model
        'bank_branch',
        'bank_address',
        'bank_phone',
        'bank_web_page',
        'desc',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());

        static::creating(function ($bankAccount) {
            if (!isset($bankAccount->company_id)) {
                $bankAccount->company_id = session('active-company-id');
            }
        });
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
