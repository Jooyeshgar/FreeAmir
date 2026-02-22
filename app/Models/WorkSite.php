<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function contractFrameworks(): BelongsToMany
    {
        return $this->belongsToMany(
            ContractFramework::class,
            'work_site_contracts',
            'work_site_id',
            'contract_framework_id'
        )->withPivot('created_at');
    }
}
