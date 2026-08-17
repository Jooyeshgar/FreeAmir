<?php

namespace App\Models;

use App\Enums\ChequeType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cheque extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'amount',
        'write_date',
        'due_date',
        'serial',
        'cheque_number',
        'sayad_number',
        'direction',
        'purpose',
        'status',
        'customer_id',
        'endorsed_to_id',
        'bank_account_id',
        'chequebook_id',
        'desc',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'write_date' => 'date',
        'due_date' => 'date',
        'direction' => ChequeType::class,
        'purpose' => ChequeType::class,
        'status' => ChequeType::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (Cheque $cheque) {
            $cheque->company_id ??= getActiveCompany();
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function endorsedTo(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'endorsed_to_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function chequebook(): BelongsTo
    {
        return $this->belongsTo(Chequebook::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ChequeHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function availableActions(): array
    {
        if ($this->purpose === ChequeType::GUARANTEE) {
            return in_array($this->status, [ChequeType::GUARANTEE_RECEIVED, ChequeType::GUARANTEE_GIVEN], true) ? ['execute', 'cancel'] : [];
        }

        if ($this->direction === ChequeType::RECEIVABLE) {
            return match ($this->status) {
                ChequeType::REGISTERED => ['deposit', 'endorse', 'return'],
                ChequeType::DEPOSITED => ['clear', 'bounce'],
                ChequeType::BOUNCED => ['deposit', 'return'],
                default => [],
            };
        }

        return match ($this->status) {
            ChequeType::ISSUED => ['clear', 'bounce', 'cancel'],
            ChequeType::BOUNCED => ['cancel'],
            default => [],
        };
    }
}
