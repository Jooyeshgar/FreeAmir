<?php

namespace App\Models;

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

        static::creating(function ($model) {
            
            $model->company_id = session('active-company-id');

        });
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
