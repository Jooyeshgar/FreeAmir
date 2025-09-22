@can('home.*')
    <li><a href="/" class="hover:rounded-xl">{{ __('Home') }}</a></li>
@endcan
@can('invoices.create')
    <li class="dropdown dropdown-hover">
        <div tabindex="0" role="button">{{ __('Operation') }}</div>
        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            <li><a href="{{ route('invoices.index') }}">{{ __('Invoice List') }}</a></li>
            <li><a href="{{ route('customers.create') }}">{{ __('Add Customer') }}</a></li>
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
@canany(['reports.journal', 'reports.ledger', 'reports.sub-ledger', 'products.index', 'product-groups.index'])
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
                            {{-- <li><a href="">{{ __('Profit and loss') }}</a></li> --}}
                        </ul>
                    </details>
                </li>
            @endcanany
            @canany(['products.index', 'product-groups.index'])
                <li>
                    <details>
                        <summary>{{ __('Warehouse') }}</summary>
                        <ul>
                            @can('products.index')
                                <li><a href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
                            @endcan
                            @can('product-groups.index')
                                <li><a href="{{ route('product-groups.index') }}">{{ __('Product Groups') }}</a>
                            @endcan
                            </li>
                        </ul>
                    </details>
                </li>
            @endcanany
            {{-- <li>
                <details>
                    <summary>{{ __('Customers') }}</summary>
                    <ul>
                        <li><a href="">{{ __('Debtors') }}</a></li>
                        <li><a href="">{{ __('Creditors') }}</a></li>
                    </ul>
                </details>
            </li> --}}
        </ul>
    </li>
@endcanany
<li class="dropdown dropdown-hover">
    <div tabindex="3" role="button">{{ __('Management') }}</div>
    <ul tabindex="3" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
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
        <li><a href="https://github.com/Jooyeshgar/FreeAmir/issues">{{ __('Support') }}</a></li>
    </ul>
</li>