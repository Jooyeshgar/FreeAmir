@php
    $topDropdownClass = 'app-main-menu-dropdown';
    $topDropdownContentClass = 'app-main-menu-panel z-50 w-max min-w-56 max-w-[calc(100vw-2rem)] overflow-x-auto';
    $scrollingTopDropdownContentClass = $topDropdownContentClass . ' max-h-[calc(100vh-6rem)] overflow-y-auto';
@endphp

@can('home.*')
    <li><a href="/">{{ __('Home') }}</a></li>
@endcan

@can('employee-portal.dashboard')
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('My Portal') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                <li><a href="{{ route('employee-portal.dashboard') }}">{{ __('Dashboard') }}</a></li>
                <li><a href="{{ route('employee-portal.attendance-logs') }}">{{ __('My Attendance') }}</a></li>
                <li><a href="{{ route('employee-portal.monthly-attendances') }}">{{ __('Monthly Attendance') }}</a></li>
                <li><a href="{{ route('employee-portal.payrolls') }}">{{ __('My Payrolls') }}</a></li>
                <li><a href="{{ route('employee-portal.personnel-requests.index') }}">{{ __('My Requests') }}</a></li>
                <li><a href="{{ route('employee-portal.employee.show') }}">{{ __('My Information') }}</a></li>
            </ul>
        </details>
    </li>
@endcan
@canany(['invoices.index', 'invoices.inactive', 'ancillary-costs.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('Invoices') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('invoices.index')
                    <li>
                        <details>
                            <summary>{{ __('Sales') }}</summary>
                            <ul>
                                @can('invoices.index')
                                    <li><a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}">{{ __('Sell List') }}</a></li>
                                    <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_sell']) }}">{{ __('Return Sell List') }}</a></li>
                                    <li><a href="{{ route('invoices.index', ['invoice_type' => 'void']) }}">{{ __('Voided Sell') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcan
                @canany(['invoices.index', 'ancillary-costs.index'])
                    <li>
                        <details>
                            <summary>{{ __('Purchases') }}</summary>
                            <ul>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}">{{ __('Buy List') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy', 'service_buy' => '1']) }}">{{ __('Buy Service') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_buy']) }}">{{ __('Return Buy List') }}</a></li>
                                <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_buy', 'service_buy' => '1']) }}">{{ __('Service Buy Return') }}</a></li>
                                @can('ancillary-costs.index')
                                    <li><a href="{{ route('ancillary-costs.index') }}">{{ __('Ancillary Cost List') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
                @can('invoices.inactive')
                    <li><a href="{{ route('invoices.inactive') }}">{{ __('Activate Confirmed Invoices') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany
@canany(['documents.index', 'documents.create'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('Accounting') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('documents.create')
                    <li><a href="{{ route('documents.create') }}">{{ __('Create Document') }}</a></li>
                @endcan
                @can('documents.index')
                    <li><a href="{{ route('documents.index') }}">{{ __('Document List') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany
@canany(['reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance', 'reports.cost-income'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('Reports') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('reports.cost-income')
                    <li><a href="{{ route('reports.cost-income') }}">{{ __('Cost and Income Dashboard') }}</a></li>
                @endcan
                @canany(['reports.documents', 'reports.journal', 'reports.ledger', 'reports.sub-ledger', 'reports.trial-balance'])
                    <li>
                        <details>
                            <summary>{{ __('Accounting') }}</summary>
                            <ul>
                                @can('reports.documents')
                                    <li><a href="{{ route('reports.documents') }}">{{ __('Documents Report') }}</a></li>
                                @endcan
                                @can('reports.journal')
                                    <li><a href="{{ route('reports.journal') }}">{{ __('Journal') }}</a></li>
                                @endcan
                                @can('reports.ledger')
                                    <li><a href="{{ route('reports.ledger') }}">{{ __('Ledger') }}</a></li>
                                @endcan
                                @can('reports.sub-ledger')
                                    <li><a href="{{ route('reports.sub-ledger') }}">{{ __('Sub Ledger') }}</a></li>
                                @endcan
                                @can('reports.trial-balance')
                                    <li><a href="{{ route('reports.trial-balance') }}">{{ __('Trial Balance') }}</a></li>
                                @endcan
                            </ul>
                        </details>
                    </li>
                @endcanany
            </ul>
        </details>
    </li>
@endcanany

@canany(['warehouse.dashboard', 'products.index', 'product-groups.index', 'services.index', 'service-groups.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('Warehouse') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('warehouse.dashboard')
                    <li><a href="{{ route('warehouse.dashboard') }}">{{ __('Warehouse Dashboard') }}</a></li>
                @endcan
                @can('products.index')
                    <li><a href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
                @endcan
                @can('product-groups.index')
                    <li><a href="{{ route('product-groups.index') }}">{{ __('Product Groups') }}</a></li>
                @endcan
                @can('services.index')
                    <li><a href="{{ route('services.index') }}">{{ __('Services') }}</a></li>
                @endcan
                @can('service-groups.index')
                    <li><a href="{{ route('service-groups.index') }}">{{ __('Service Groups') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['salary.payrolls.dashboard', 'salary.payrolls.index', 'hr.employees.index', 'hr.personnel-requests.index', 'attendance.attendance-logs.index',
    'attendance.monthly-attendances.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('HR') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('salary.payrolls.dashboard')
                    <li><a href="{{ route('salary.payrolls.dashboard') }}">{{ __('Payroll Dashboard') }}</a></li>
                @endcan
                @can('salary.payrolls.index')
                    <li><a href="{{ route('salary.payrolls.index') }}">{{ __('Payrolls') }}</a></li>
                @endcan
                @can('hr.employees.index')
                    <li><a href="{{ route('hr.employees.index') }}">{{ __('Employees') }}</a></li>
                @endcan
                @can('hr.personnel-requests.index')
                    <li><a href="{{ route('hr.personnel-requests.index') }}">{{ __('Personnel Requests') }}</a></li>
                @endcan
                @can('attendance.attendance-logs.index')
                    <li><a href="{{ route('attendance.attendance-logs.index') }}">{{ __('Attendance Logs') }}</a></li>
                @endcan
                @can('attendance.monthly-attendances.index')
                    <li><a href="{{ route('attendance.monthly-attendances.index') }}">{{ __('Monthly Attendances') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

@canany(['crm.dashboard', 'customers.index', 'customer-groups.index'])
    <li>
        <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
            <summary>{{ __('CRM') }}</summary>
            <ul class="{{ $topDropdownContentClass }}">
                @can('crm.dashboard')
                    <li><a href="{{ route('crm.dashboard') }}">{{ __('CRM Dashboard') }}</a></li>
                @endcan
                @can('customers.index')
                    <li><a href="{{ route('customers.index') }}">{{ __('Customers') }}</a></li>
                @endcan
                @can('customer-groups.index')
                    <li><a href="{{ route('customer-groups.index') }}">{{ __('Customer Groups') }}</a></li>
                @endcan
            </ul>
        </details>
    </li>
@endcanany

<li>
    <details class="{{ $topDropdownClass }}" data-main-menu-dropdown>
        <summary>{{ __('Management') }}</summary>
        <ul class="{{ $scrollingTopDropdownContentClass }}">

            {{-- HR, Attendance & Salary --}}
            @canany(['hr.org-charts.index', 'hr.organization-units.index', 'attendance.work-shifts.index', 'salary.tax-slabs.index', 'salary.work-sites.index',
                'salary.work-site-contracts.index', 'salary.public-holidays.index', 'salary.payroll-elements.index', 'salary.salary-decrees.index'])
                <li>
                    <details>
                        <summary>{{ __('HR & Organization') }}</summary>
                        <ul>
                            @can('hr.org-charts.index')
                                <li><a href="{{ route('hr.org-charts.index') }}">{{ __('Organization Chart') }}</a></li>
                            @endcan
                            @can('hr.organization-units.index')
                                <li><a href="{{ route('hr.organization-units.index') }}">{{ __('Organization Units') }}</a></li>
                            @endcan
                            @can('attendance.work-shifts.index')
                                <li><a href="{{ route('attendance.work-shifts.index') }}">{{ __('Work Shifts') }}</a></li>
                            @endcan
                            @can('salary.tax-slabs.index')
                                <li><a href="{{ route('salary.tax-slabs.index') }}">{{ __('Yearly Tax Slabs') }}</a></li>
                            @endcan
                            @can('salary.work-sites.index')
                                <li><a href="{{ route('salary.work-sites.index') }}">{{ __('Work Sites') }}</a></li>
                            @endcan
                            @can('salary.work-site-contracts.index')
                                <li><a href="{{ route('salary.work-site-contracts.index') }}">{{ __('Work Site Contracts') }}</a></li>
                            @endcan
                            @can('salary.public-holidays.index')
                                <li><a href="{{ route('salary.public-holidays.index') }}">{{ __('Public Holidays') }}</a></li>
                            @endcan
                            @can('salary.payroll-elements.index')
                                <li><a href="{{ route('salary.payroll-elements.index') }}">{{ __('Payroll Elements') }}</a></li>
                            @endcan
                            @can('salary.salary-decrees.index')
                                <li><a href="{{ route('salary.salary-decrees.index') }}">{{ __('Salary Decrees') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
            @endcanany

            {{-- Finance --}}
            @canany(['bank-accounts.index', 'banks.index', 'subjects.index', 'documents.sort-numbers'])
                <li>
                    <details>
                        <summary>{{ __('Finance') }}</summary>
                        <ul>
                            @can('subjects.index')
                                <li><a href="{{ route('subjects.index') }}">{{ __('Subjects') }}</a></li>
                            @endcan
                            @can('bank-accounts.index')
                                <li><a href="{{ route('bank-accounts.index') }}">{{ __('Bank Accounts') }}</a></li>
                            @endcan
                            @can('banks.index')
                                <li><a href="{{ route('banks.index') }}">{{ __('Banks') }}</a></li>
                            @endcan
                            @can('documents.sort-numbers')
                                <li><a href="{{ route('documents.sort-numbers') }}">{{ __('Sort Documents Number') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
            @endcanany

            {{-- System --}}
            @canany(['companies.index', 'users.index', 'permissions.index', 'roles.index', 'configs.index', 'backups.create'])
                <li>
                    <details>
                        <summary>{{ __('System') }}</summary>
                        <ul>
                            @can('companies.index')
                                <li><a href="{{ route('companies.index') }}">{{ __('Companies') }}</a></li>
                            @endcan
                            @can('users.index')
                                <li><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
                            @endcan
                            @can('permissions.index')
                                <li><a href="{{ route('permissions.index') }}">{{ __('Permissions') }}</a></li>
                            @endcan
                            @can('roles.index')
                                <li><a href="{{ route('roles.index') }}">{{ __('Roles') }}</a></li>
                            @endcan
                            @can('configs.index')
                                <li><a href="{{ route('configs.index') }}">{{ __('Configs') }}</a></li>
                            @endcan
                            @can('backups.create')
                                <li><a href="{{ route('backups.create') }}">{{ __('Backup') }}</a></li>
                                <li><a href="{{ route('backups.upload') }}">{{ __('Upload Backup') }}</a></li>
                            @endcan
                        </ul>
                    </details>
                </li>
            @endcanany

            <li><a href="{{ route('about') }}">{{ __('about.about') }}</a></li>
        </ul>
    </details>
</li>
