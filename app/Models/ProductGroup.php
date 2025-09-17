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
        'vat',
        'company_id',
        'subject_id',
    ];

    protected $attributes = [
        'vat' => 0,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());

        static::creating(function ($model) {
            $model->company_id = session('active-company-id');
            $model->subject_id = config('amir.product');
        });
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

    public function products()
    {
        return $this->hasMany(Product::class, 'group', 'id');
    }
}
