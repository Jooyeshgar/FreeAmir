<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject_id',
        'phone',
        'cell',
        'fax',
        'address',
        'postal_code',
        'email',
        'ecnmcs_code',
        'personal_code',
        'web_page',
        'responsible',
        'connector',
        'group_id',
        'desc',
        'balance',
        'credit',
        'rep_via_email',
        'acc_name_1',
        'acc_no_1',
        'acc_bank_1',
        'acc_name_2',
        'acc_no_2',
        'acc_bank_2',
        'type_buyer',
        'type_seller',
        'type_mate',
        'type_agent',
        'introducer_id',
        'commission',
        'marked',
        'reason',
        'disc_rate',
        'company_id',
    ];

    protected $attributes = [
        'connector' => '',
        'cell' => '',
        'balance' => 0,
        'credit' => 0,
        'type_buyer' => 0,
        'type_seller' => 0,
        'type_mate' => 0,
        'type_agent' => 0,
        'commission' => '',
        'marked' => 0,
        'reason' => '',
        'disc_rate' => '',
        'address' => '',
        'web_page' => '',
        'responsible' => '',
        'desc' => '',
        'postal_code' => '',
    ];

    protected static function booted()
    {
        static::created(function ($customer) {
            $parentGroup = $customer->group;
            $subject = $customer->subject()->create([
                'name' => $customer->name,
                'parent_id' => $parentGroup->subject_id ?? 0,
                'company_id' => session('active-company-id'),
            ]);

            $customer->update(['subject_id' => $subject->id]);
        });

        static::deleting(function ($customer) {
            // Delete the related subject when the customer is deleted
            if ($customer->subject) {
                $customer->subject->delete();
            }
        });
    }

    public function subject()
    {
        return $this->morphOne(Subject::class, 'subjectable');
    }

    public function group()
    {
        return $this->belongsTo(CustomerGroup::class, 'group_id');
    }

    public function introducer()
    {
        return $this->belongsTo(Customer::class, 'introducer_id');
    }
}
