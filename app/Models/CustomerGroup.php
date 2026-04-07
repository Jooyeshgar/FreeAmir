<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'subject_id',
        'name',
        'description',
        'company_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($model) {
            $model->company_id ??= getActiveCompany();
        });
    }

    public function subject()
    {
        return $this->morphOne(Subject::class, 'subjectable');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'group_id', 'id');
    }
}
