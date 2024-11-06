<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
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
    ];

    protected static function booted()
    {
        static::creating(function ($customer) {

            // if (!$customer->subject_id) {
            //     // Find or create a subject under the specified parent
            //     $subject = Subject::firstOrCreate([
            //         'parent_id' => $specificParentId, // Replace with the desired parent ID
            //     ]);

            //     $customer->subject_id = $subject->id;
            // }
        });
        static::created(function ($customer) {
            $customer->subject()->create([
                'name' => $customer->name,
                'company_id' => session('active-company-id'),
                'code' => request('code'),
                'parent_id' => config('amir.cust_subject'),
            ]);
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
