<?php

namespace App\Enums;

use Illuminate\Support\Facades\Lang;

enum FiscalYearSection: string
{
    case CONFIGS = 'configs';
    case BANKS = 'banks'; // Represents banks + bank_accounts
    case CUSTOMERS = 'customers'; // Represents customer_groups + customers + comments
    case PRODUCTS = 'products'; // Represents product_groups + products
    case SERVICES = 'services'; // Represents service_groups + services
    case SUBJECTS = 'subjects';
    case DOCUMENTS = 'documents'; // Represents documents + transactions + document_files
    case INVOICES = 'invoices'; // Represents invoices + ancillary_costs
    case CHEQUES = 'cheques'; //  Represents cheques + cheques_history
    case EMPLOYEE = 'employee'; // Represents employees + org_charts + work_sites + work_site_contracts + work_shifts + salary_decrees + monthly_attendances + attendance_logs + payrolls + personnel_requests

    public function label(): string
    {
        return Lang::get($this->value);
    }

    public static function cli(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = $case->label();

            return $carry;
        }, []);
    }

    public static function ui(): array
    {
        return [
            self::CONFIGS->value => self::CONFIGS->label(),
            self::BANKS->value => self::BANKS->label(),
            self::CUSTOMERS->value => self::CUSTOMERS->label(),
            self::PRODUCTS->value => self::PRODUCTS->label(),
            self::SERVICES->value => self::SERVICES->label(),
            self::SUBJECTS->value => self::SUBJECTS->label(),
            self::EMPLOYEE->value => self::EMPLOYEE->label(),
        ];
    }
}
