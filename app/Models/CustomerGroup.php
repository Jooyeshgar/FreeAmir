<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
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

        static::addGlobalScope(new FiscalYearScope());

        static::creating(function ($model) {
            $model->company_id = session('active-company-id');
        });

        static::created(function ($customertGroup) {
            $subject = $customertGroup->subject()->create([
                'name' => $customertGroup->name,
                'parent_id' => config('amir.cust_subject'),
                'company_id' => session('active-company-id'),
            ]);

            $customertGroup->update(['subject_id' => $subject->id]);
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
