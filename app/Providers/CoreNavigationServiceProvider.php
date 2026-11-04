<?php

namespace App\Providers;

use App\Extensions\MenuItem;
use App\Extensions\NavigationRegistry;
use Illuminate\Support\ServiceProvider;

class CoreNavigationServiceProvider extends ServiceProvider
{
    public function boot(NavigationRegistry $navigation): void
    {
        foreach ($this->definitions() as $definition) {
            $navigation->register(new MenuItem(...$definition));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $items = [];
        $group = static function (string $id, string $label, ?string $parent, int $priority, array $permissions = []) use (&$items): void {
            $items[] = compact('id', 'label', 'parent', 'priority', 'permissions');
        };
        $link = static function (string $id, string $label, string $route, ?string $parent, int $priority, array $permissions = [], array $routeParameters = []) use (&$items): void {
            $items[] = compact('id', 'label', 'route', 'parent', 'priority', 'permissions', 'routeParameters');
        };

        $link('core.home', 'Home', 'home', null, 100, ['home.*']);

        $group('core.portal', 'My Portal', null, 200, ['employee-portal.dashboard']);
        foreach ([
            ['dashboard', 'Dashboard', 'employee-portal.dashboard'],
            ['attendance', 'My Attendance', 'employee-portal.attendance-logs'],
            ['monthly-attendance', 'Monthly Attendance', 'employee-portal.monthly-attendances'],
            ['payrolls', 'My Payrolls', 'employee-portal.payrolls'],
            ['requests', 'My Requests', 'employee-portal.personnel-requests.index'],
            ['information', 'My Information', 'employee-portal.employee.show'],
        ] as $index => [$id, $label, $route]) {
            $link("core.portal.$id", $label, $route, 'core.portal', ($index + 1) * 100);
        }

        $group('core.invoices', 'Invoices', null, 300, [
            'invoices.dashboard', 'invoices.index', 'invoices.inactive', 'customers.create',
            'ancillary-costs.index', 'invoices.moadian-histories.index',
        ]);
        $link('core.invoices.dashboard', 'Invoice Dashboard', 'invoices.dashboard', 'core.invoices', 100, ['invoices.dashboard']);
        $group('core.invoices.sales', 'Sales', 'core.invoices', 200, ['invoices.index', 'invoices.moadian-histories.index']);
        $link('core.invoices.sales.sell', 'Sell List', 'invoices.index', 'core.invoices.sales', 100, ['invoices.index'], ['invoice_type' => 'sell']);
        $link('core.invoices.sales.return-sell', 'Return Sell List', 'invoices.index', 'core.invoices.sales', 200, ['invoices.index'], ['invoice_type' => 'return_sell']);
        $link('core.invoices.sales.voided-sell', 'Voided Sell', 'invoices.index', 'core.invoices.sales', 300, ['invoices.index'], ['invoice_type' => 'void']);
        $link('core.invoices.sales.moadian-histories', 'Moadian Histories', 'invoices.moadian-histories.index', 'core.invoices.sales', 400, ['invoices.moadian-histories.index']);
        $group('core.invoices.purchases', 'Purchases', 'core.invoices', 300, ['invoices.index', 'ancillary-costs.index']);
        $link('core.invoices.purchases.buy', 'Buy List', 'invoices.index', 'core.invoices.purchases', 100, ['invoices.index'], ['invoice_type' => 'buy']);
        $link('core.invoices.purchases.buy-service', 'Buy Service', 'invoices.index', 'core.invoices.purchases', 200, ['invoices.index'], ['invoice_type' => 'buy', 'service_buy' => '1']);
        $link('core.invoices.purchases.return-buy', 'Return Buy List', 'invoices.index', 'core.invoices.purchases', 300, ['invoices.index'], ['invoice_type' => 'return_buy']);
        $link('core.invoices.purchases.service-buy-return', 'Service Buy Return', 'invoices.index', 'core.invoices.purchases', 400, ['invoices.index'], ['invoice_type' => 'return_buy', 'service_buy' => '1']);
        $link('core.invoices.purchases.ancillary-costs', 'Ancillary Cost List', 'ancillary-costs.index', 'core.invoices.purchases', 500, ['ancillary-costs.index']);
        $link('core.invoices.beginning-inventory', 'Beginning Inventory', 'invoices.index', 'core.invoices', 400, ['invoices.index'], ['invoice_type' => 'beginning_inventory']);
        $link('core.invoices.inactive', 'Activate Confirmed Invoices', 'invoices.inactive', 'core.invoices', 500, ['invoices.inactive']);
        $link('core.invoices.add-customer', 'Add Customer', 'customers.create', 'core.invoices', 600, ['customers.create']);

        $group('core.accounting', 'Accounting', null, 400, ['documents.index', 'documents.create', 'cheques.index']);
        $link('core.accounting.create-document', 'Create Document', 'documents.create', 'core.accounting', 100, ['documents.create']);
        $link('core.accounting.documents', 'Document List', 'documents.index', 'core.accounting', 200, ['documents.index']);
        $link('core.accounting.cheques', 'Cheque Management', 'cheques.index', 'core.accounting', 300, ['cheques.index']);

        $group('core.reports', 'Reports', null, 500, [
            'reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance',
            'reports.inventory-turnover', 'commercial-ledgers.index', 'reports.cost-income', 'budgets.index', 'reports.company-overview',
        ]);
        $link('core.reports.company-overview', 'Company overview', 'reports.company-overview', 'core.reports', 100, ['reports.company-overview']);
        $link('core.reports.cost-income', 'Cost and Income Dashboard', 'reports.cost-income', 'core.reports', 200, ['reports.cost-income']);
        $link('core.reports.monthly-workbench', 'Monthly Income and Expense Workbench', 'budgets.index', 'core.reports', 300, ['budgets.index']);
        $link('core.reports.commercial-ledgers', 'Commercial Ledgers', 'commercial-ledgers.index', 'core.reports', 400, ['commercial-ledgers.index']);
        $group('core.reports.accounting', 'Accounting', 'core.reports', 500, [
            'reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance', 'reports.inventory-turnover',
        ]);
        foreach ([
            ['documents', 'Documents Report', 'reports.documents'],
            ['journal', 'Journal', 'reports.journal'],
            ['ledger', 'Ledger', 'reports.ledger'],
            ['sub-ledger', 'Sub Ledger', 'reports.sub-ledger'],
            ['trial-balance', 'Trial Balance', 'reports.trial-balance'],
            ['inventory-turnover', 'Inventory Turnover', 'reports.inventory-turnover'],
        ] as $index => [$id, $label, $route]) {
            $link("core.reports.accounting.$id", $label, $route, 'core.reports.accounting', ($index + 1) * 100, [$route]);
        }

        $group('core.warehouse', 'Warehouse', null, 600, ['warehouse.dashboard', 'warehouses.index', 'products.index', 'product-groups.index', 'services.index', 'service-groups.index']);
        foreach ([
            ['dashboard', 'Warehouse Dashboard', 'warehouse.dashboard'], ['warehouses', 'Warehouses', 'warehouses.index'],
            ['products', 'Products', 'products.index'], ['product-groups', 'Product Groups', 'product-groups.index'],
            ['services', 'Services', 'services.index'], ['service-groups', 'Service Groups', 'service-groups.index'],
        ] as $index => [$id, $label, $route]) {
            $link("core.warehouse.$id", $label, $route, 'core.warehouse', ($index + 1) * 100, [$route]);
        }

        $group('core.hr', 'HR', null, 700, ['salary.payrolls.dashboard', 'salary.payrolls.index', 'hr.employees.index', 'hr.personnel-requests.index', 'attendance.attendance-logs.index', 'attendance.monthly-attendances.index']);
        foreach ([
            ['payroll-dashboard', 'Payroll Dashboard', 'salary.payrolls.dashboard'], ['payrolls', 'Payrolls', 'salary.payrolls.index'],
            ['employees', 'Employees', 'hr.employees.index'], ['personnel-requests', 'Personnel Requests', 'hr.personnel-requests.index'],
            ['attendance-logs', 'Attendance Logs', 'attendance.attendance-logs.index'], ['monthly-attendances', 'Monthly Attendances', 'attendance.monthly-attendances.index'],
        ] as $index => [$id, $label, $route]) {
            $link("core.hr.$id", $label, $route, 'core.hr', ($index + 1) * 100, [$route]);
        }

        $group('core.crm', 'CRM', null, 800, ['crm.dashboard', 'customers.index', 'customer-groups.index']);
        foreach ([
            ['dashboard', 'CRM Dashboard', 'crm.dashboard'], ['customers', 'Customers', 'customers.index'], ['customer-groups', 'Customer Groups', 'customer-groups.index'],
        ] as $index => [$id, $label, $route]) {
            $link("core.crm.$id", $label, $route, 'core.crm', ($index + 1) * 100, [$route]);
        }

        $group('core.management', 'Management', null, 900);
        $group('core.management.hr-organization', 'HR & Organization', 'core.management', 100, [
            'hr.org-charts.index', 'hr.organization-units.index', 'attendance.work-shifts.index', 'salary.payrolls.index',
            'salary.tax-slabs.index', 'salary.work-sites.index', 'salary.work-site-contracts.index', 'salary.public-holidays.index',
            'salary.payroll-elements.index', 'salary.salary-decrees.index',
        ]);
        foreach ([
            ['organization-charts', 'Organization Chart', 'hr.org-charts.index'], ['organization-units', 'Organization Units', 'hr.organization-units.index'],
            ['work-shifts', 'Work Shifts', 'attendance.work-shifts.index'], ['payrolls', 'Payrolls', 'salary.payrolls.index'],
            ['tax-slabs', 'Yearly Tax Slabs', 'salary.tax-slabs.index'], ['work-sites', 'Work Sites', 'salary.work-sites.index'],
            ['work-site-contracts', 'Work Site Contracts', 'salary.work-site-contracts.index'], ['public-holidays', 'Public Holidays', 'salary.public-holidays.index'],
            ['payroll-elements', 'Payroll Elements', 'salary.payroll-elements.index'], ['salary-decrees', 'Salary Decrees', 'salary.salary-decrees.index'],
        ] as $index => [$id, $label, $route]) {
            $link("core.management.hr-organization.$id", $label, $route, 'core.management.hr-organization', ($index + 1) * 100, [$route]);
        }
        $group('core.management.finance', 'Finance', 'core.management', 200, ['bank-accounts.index', 'banks.index', 'chequebooks.index', 'subjects.index', 'documents.sort-numbers']);
        foreach ([
            ['subjects', 'Subjects', 'subjects.index'], ['bank-accounts', 'Bank Accounts', 'bank-accounts.index'], ['banks', 'Banks', 'banks.index'],
            ['chequebooks', 'Chequebooks', 'chequebooks.index'], ['sort-documents', 'Sort Documents Number', 'documents.sort-numbers'],
        ] as $index => [$id, $label, $route]) {
            $link("core.management.finance.$id", $label, $route, 'core.management.finance', ($index + 1) * 100, [$route]);
        }
        $group('core.management.system', 'System', 'core.management', 300, ['companies.index', 'fiscal-years.index', 'users.index', 'configs.index', 'backups.create']);
        foreach ([
            ['companies', 'Companies', 'companies.index', ['companies.index']], ['fiscal-years', 'Fiscal years', 'fiscal-years.index', ['fiscal-years.index']],
            ['users', 'Users', 'users.index', ['users.index']], ['configs', 'Configs', 'configs.index', ['configs.index']],
            ['backup', 'Backup', 'backups.create', ['backups.create']], ['upload-backup', 'Upload Backup', 'backups.upload', ['backups.create']],
        ] as $index => [$id, $label, $route, $permissions]) {
            $link("core.management.system.$id", $label, $route, 'core.management.system', ($index + 1) * 100, $permissions);
        }
        $link('core.management.about', 'about.about', 'about', 'core.management', 500);

        return array_map(static function (array $definition): array {
            $definition['permissionMode'] = 'any';
            $definition['visible'] = null;

            return $definition;
        }, $items);
    }
}
