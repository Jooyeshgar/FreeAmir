<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'personnel_code',
        'father_name',
        'nationality',
        'identity_number',
        'national_code',
        'passport_number',
        'marital_status',
        'gender',
        'contact_number',
        'address',
        'insurance_number',
        'insurance_type',
        'children_count',
        'bank_id',
        'account_number',
        'card_number',
        'iban',
        'detailed_code',
        'contract_start_date',
        'employment_type',
        'contract_type',
        'birth_place',
        'organizational_chart_id',
        'military_status',
        'workhouse_id',
    ];

    // Relationships
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function organizationalChart()
    {
        return $this->belongsTo(OrganizationalChart::class);
    }

    public function workhouse()
    {
        return $this->belongsTo(Workhouse::class);
    }

    public function salarySlips()
    {
        return $this->belongsToMany(SalarySlip::class);
    }
}
