<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkbook extends Model
{
    protected $fillable = [
        'company_id',
        'bank_account_id',
        'title',
        'serial_prefix',
        'start_leaf_number',
        'end_leaf_number',
        'next_leaf_number',
        'print_settings',
        'is_active',
    ];

    protected $casts = [
        'start_leaf_number' => 'integer',
        'end_leaf_number' => 'integer',
        'next_leaf_number' => 'integer',
        'print_settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
        static::creating(function (Checkbook $checkbook) {
            $checkbook->company_id ??= getActiveCompany();
            $checkbook->next_leaf_number ??= $checkbook->start_leaf_number;
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

    public function contains(int $leaf): bool
    {
        return $leaf >= $this->start_leaf_number && $leaf <= $this->end_leaf_number;
    }

    public function serialFor(int $leaf): string
    {
        return ($this->serial_prefix ?? '').$leaf;
    }

    public function remainingLeaves(): int
    {
        return max(0, $this->end_leaf_number - $this->next_leaf_number + 1);
    }
}
