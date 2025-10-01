<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use App\Services\SubjectCreatorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'code',
        'name',
        'sstid',
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
            $model->company_id ??= session('active-company-id');
        });

        static::created(function ($productGroup) {
            $subject = app(SubjectCreatorService::class)->createSubject([
                'name' => $productGroup->name,
                'parent_id' => config('amir.product'),
                'company_id' => $productGroup->company_id,
            ]);
            $productGroup->subject()->save($subject);

            $productGroup->update(['subject_id' => $subject->id]);
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

    public function subject()
    {
        return $this->morphOne(Subject::class, 'subjectable');
    }
}
