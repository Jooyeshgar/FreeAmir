@can('home.*')
    <li><a href="/" class="hover:rounded-xl">{{ __('Home') }}</a></li>
@endcan
@can('invoices.create')
    <li class="dropdown dropdown-hover">
        <div tabindex="0" role="button">{{ __('Operation') }}</div>
        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            <li><a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}">{{ __('Invoice Sell List') }}</a></li>
            <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy', 'service_buy' => '1']) }}">{{ __('Service Buy Invoice') }}</a></li>
            <li><a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}">{{ __('Invoice Buy List') }}</a></li>
            <!-- <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_buy']) }}">{{ __('Invoice Return Buy List') }}</a></li> -->
            <!-- <li><a href="{{ route('invoices.index', ['invoice_type' => 'return_sell']) }}">{{ __('Invoice Return Sell List') }}</a></li> -->
            <li><a href="{{ route('customers.create') }}">{{ __('Add Customer') }}</a></li>
            <li><a href="{{ route('ancillary-costs.index') }}">{{ __('Ancillary Cost List') }}</a></li>
        </ul>
    </li>
@endcan
@canany(['documents.index', 'documents.create', 'documents.edit'])
    <li class="dropdown dropdown-hover">
        <div tabindex="1" role="button">{{ __('Accounting') }}</div>
        <ul tabindex="1" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            <li><a href="{{ route('documents.create') }}">{{ __('Create Document') }}</a></li>
            <li><a href="{{ route('documents.index') }}">{{ __('Document List') }}</a></li>
        </ul>
    </li>
@endcanany
@canany(['reports.journal', 'reports.ledger', 'reports.sub-ledger'])
    <li class="dropdown dropdown-hover">
        <div tabindex="2" role="button">{{ __('Reports') }}</div>
        <ul tabindex="2" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            @canany(['reports.journal', 'reports.ledger', 'reports.sub-ledger'])
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
    </li>
@endcanany

@canany(['products.index', 'product-groups.index'])
    <li class="dropdown dropdown-hover">
        <div tabindex="3" role="button">{{ __('Warehouse') }}</div>
        <ul tabindex="3" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            <li><a href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
            <li><a href="{{ route('product-groups.index') }}">{{ __('Product Groups') }}</a></li>
        </ul>
    </li>
@endcanany

<li class="dropdown dropdown-hover">
    <div tabindex="4" role="button">{{ __('Management') }}</div>
    <ul tabindex="4" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
        @canany(['org-charts.index', 'org-charts.create', 'org-charts.edit'])
            <li><a href="{{ route('org-charts.index') }}">{{ __('Organization Chart') }}</a></li>
        @endcanany
        @canany(['subjects.index', 'subjects.create', 'subjects.edit'])
            <li><a href="{{ route('subjects.index') }}">{{ __('Subjects') }}</a></li>
        @endcanany
        @canany(['bank-accounts.index', 'bank-accounts.create', 'bank-accounts.edit'])
            <li><a href="{{ route('bank-accounts.index') }}">{{ __('Bank Accounts') }}</a></li>
        @endcanany
        @canany(['customers.index', 'customers.create', 'customers.edit'])
            <li><a href="{{ route('customers.index') }}">{{ __('Customers') }} </a></li>
        @endcanany
        @canany(['customer-groups.index', 'customer-groups.create', 'customer-groups.edit'])
            <li><a href="{{ route('customer-groups.index') }}">{{ __('Customer Groups') }} </a></li>
        @endcanany
        @canany(['services.index', 'services.create', 'services.edit'])
            <li><a href="{{ route('services.index') }}">{{ __('Services') }}</a></li>
        @endcanany
        @canany(['service-groups.index', 'service-groups.create', 'service-groups.edit'])
            <li><a href="{{ route('service-groups.index') }}">{{ __('Service Groups') }}</a></li>
        @endcanany
        @canany(['companies.index', 'companies.create', 'companies.edit'])
            <li><a href="{{ route('companies.index') }}">{{ __('Companies') }} </a></li>
        @endcanany
        @canany(['banks.index', 'banks.create', 'banks.edit'])
            <li><a href="{{ route('banks.index') }}">{{ __('Banks') }}</a></li>
        @endcanany
        @canany(['management.users.index', 'management.users.create', 'management.users.edit'])
            <li><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
        @endcanany
        @canany(['management.permissions.index', 'management.permissions.create', 'management.permissions.edit'])
            <li><a href="{{ route('permissions.index') }}">{{ __('Permissions') }}</a></li>
        @endcanany
        @canany(['management.roles.index', 'management.roles.create', 'management.roles.edit'])
            <li><a href="{{ route('roles.index') }}">{{ __('Roles') }}</a></li>
        @endcanany
        @canany(['management.configs.index', 'management.configs.create', 'management.configs.edit'])
            <li><a href="{{ route('configs.index') }}">{{ __('Configs') }}</a></li>
        @endcanany
        @canany(['salary.tax-slabs.index', 'salary.tax-slabs.create', 'salary.tax-slabs.edit', 'salary.work-sites.index', 'salary.work-sites.create',
            'salary.work-sites.edit', 'salary.public-holidays.index', 'salary.public-holidays.create', 'salary.public-holidays.edit',
            'salary.payroll-elements.index', 'salary.payroll-elements.create', 'salary.payroll-elements.edit'])
            <li>
                <details>
                    <summary>{{ __('Salary') }}</summary>
                    <ul>
                        @can('salary.tax-slabs.index')
                            <li><a href="{{ route('tax-slabs.index') }}">{{ __('Tax Slabs') }}</a></li>
                        @endcan
                        @can('salary.work-sites.index')
                            <li><a href="{{ route('work-sites.index') }}">{{ __('Work Sites') }}</a></li>
                        @endcan
                        @can('salary.work-site-contracts.index')
                            <li><a href="{{ route('work-site-contracts.index') }}">{{ __('Work Site Contracts') }}</a></li>
                        @endcan
                        @can('salary.public-holidays.index')
                            <li><a href="{{ route('public-holidays.index') }}">{{ __('Public Holidays') }}</a></li>
                        @endcan
                        @can('salary.payroll-elements.index')
                            <li><a href="{{ route('payroll-elements.index') }}">{{ __('Payroll Elements') }}</a></li>
                        @endcan
                    </ul>
                </details>
            </li>
        @endcanany
        <li><a href="https://github.com/Jooyeshgar/FreeAmir/issues">{{ __('Support') }}</a></li>
    </ul>
</li>
