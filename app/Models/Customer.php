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

    public function subject()
    {
        return $this->belongsTo(Subject::class);
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
