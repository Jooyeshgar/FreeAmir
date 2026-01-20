<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use App\Services\SubjectService;
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

        static::created(function ($customerGroup) {
            $subject = app(SubjectService::class)->createSubject([
                'name' => $customerGroup->name,
                'parent_id' => config('amir.cust_subject'),
                'company_id' => $customerGroup->company_id,
            ]);

            // Attach the created subject via the morphOne relation
            $customerGroup->subject()->save($subject);

            $customerGroup->updateQuietly(['subject_id' => $subject->id]);
        });

        static::updated(function ($customerGroup) {
            if (! $customerGroup->wasChanged(['name', 'company_id'])) {
                return;
            }

            $subject = Subject::find($customerGroup->subject_id);

            if (! $subject) {
                $subject = app(SubjectService::class)->createSubject([
                    'name' => $customerGroup->name,
                    'parent_id' => config('amir.cust_subject'),
                    'company_id' => $customerGroup->company_id,
                ]);
                $customerGroup->subject()->save($subject);
                $customerGroup->update(['subject_id' => $subject->id]);

                return;
            }

            $updates = [];

            if ($customerGroup->wasChanged('name')) {
                $updates['name'] = $customerGroup->name;
            }

            if ($customerGroup->wasChanged('company_id')) {
                $updates['company_id'] = $customerGroup->company_id;
            }

            if (! empty($updates)) {
                $subject->forceFill($updates)->saveQuietly();
            }
        });

        static::deleting(function ($customerGroup) {
            $subject = Subject::find($customerGroup->subject_id);

            if (! $subject) {
                $subject->delete();
            }

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
