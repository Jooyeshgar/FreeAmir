<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELDS = [
        'name', 'logo', 'address', 'economical_code', 'national_code',
        'postal_code', 'phone_number', 'currency', 'certificate_path',
        'private_key_path', 'moadian_username', 'tax_id',
    ];

    public function up(): void
    {
        foreach (DB::table('company_identities')->orderBy('id')->get() as $identity) {
            $details = [];
            foreach (self::FIELDS as $field) {
                if ($field !== 'currency' || $identity->{$field} !== null) {
                    $details[$field] = $identity->{$field};
                }
            }

            DB::table('companies')->whereIn('id', DB::table('fiscal_years')->where('company_identity_id', $identity->id)->select('legacy_company_id'))->update($details);
        }
    }

    public function down(): void {}
};
