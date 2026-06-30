<?php

namespace App\Models;

use AliMousavi\Filoquent\Traits\Filterable;
use App\Models\Scopes\FiscalYearScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    use Filterable;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'date',
        'name',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = $value
            ? Carbon::parse($value)->format('Y-m-d')
            : null;
    }
}
