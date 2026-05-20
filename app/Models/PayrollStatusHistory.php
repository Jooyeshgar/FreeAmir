<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollStatusHistory extends Model
{
    protected $fillable = [
        'payroll_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'note',
    ];

    protected $casts = [
        'from_status' => PayrollStatus::class,
        'to_status' => PayrollStatus::class,
        'changed_at' => 'datetime',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
