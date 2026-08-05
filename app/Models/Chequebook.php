<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chequebook extends Model
{
    protected $fillable = [
        'company_id',
        'bank_account_id',
        'serial_prefix',
        'first_leaf',
        'last_leaf',
        'next_leaf',
        'desc',
    ];

    protected $casts = [
        'first_leaf' => 'integer',
        'last_leaf' => 'integer',
        'next_leaf' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (Chequebook $chequebook) {
            $chequebook->company_id ??= getActiveCompany();
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheque::class);
    }

    public function displayName(): string
    {
        $range = localizeNumber($this->first_leaf).'–'.localizeNumber($this->last_leaf);
        $prefix = $this->serial_prefix ? $this->serial_prefix.' ' : '';

        return trim($this->bankAccount?->name.' — '.$prefix.$range);
    }
}
