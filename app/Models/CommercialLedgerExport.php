<?php

namespace App\Models;

use App\Enums\CommercialLedgerType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialLedgerExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'creator_id',
        'from_date',
        'to_date',
        'format',
        'seal_tracking_code',
        'ledger_type',
        'status',
        'file_path',
        'row_count',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'ledger_type' => CommercialLedgerType::class,
        'row_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
