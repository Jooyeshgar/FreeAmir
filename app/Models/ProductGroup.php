<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'buyId',
        'sellId',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());
    }

    // Define relationships with other models (e.g., Subject)

    public function buySubject()
    {
        return $this->belongsTo(Subject::class, 'buyId');
    }

    public function sellSubject()
    {
        return $this->belongsTo(Subject::class, 'sellId');
    }

    // Define any other methods as needed
}
