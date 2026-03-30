<?php

namespace App\Models;

use App\Enums\ChequeBookStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChequeBook extends Model
{
    protected $fillable = [
        'title',
        'issued_at',
        'is_sayad',
        'status',
        'desc',
        'company_id',
        'bank_account_id',
    ];

    protected $casts = [
        'status' => ChequeBookStatus::class,
        'is_sayad' => 'boolean',
        'issued_at' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }
}
