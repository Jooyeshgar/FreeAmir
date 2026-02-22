<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContractFramework extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workSites(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkSite::class,
            'work_site_contracts',
            'contract_framework_id',
            'work_site_id'
        )->withPivot('created_at');
    }
}
