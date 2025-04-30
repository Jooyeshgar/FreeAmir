@can('home.*')
    <li><a href="/" class="hover:rounded-xl">{{ __('Home') }}</a></li>
@endcan
{{-- @can('customers.create')
    <li class="dropdown dropdown-hover">
        <div tabindex="0" role="button">{{ __('Operation') }}</div>
        <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            <li><a href="">{{ __('Receive and pay') }}</a></li>
            <li><a href="">{{ __('Registration of sales invoice') }}</a></li>
            <li><a href="">{{ __('Registration of purchase invoice') }}</a></li>
            <li><a href="{{ route('customers.create') }}">{{ __('Add Customer') }}</a></li>
        </ul>
    </li>
@endcan --}}
@can('documents.*')
    <li class="dropdown dropdown-hover">
        <div tabindex="1" role="button">{{ __('Accounting') }}</div>
        <ul tabindex="1" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            <li><a href="{{ route('documents.create') }}">{{ __('Create Document') }}</a></li>
            <li><a href="{{ route('documents.index') }}">{{ __('Document List') }}</a></li>
        </ul>
    </li>
@endcan
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
                                <li><a href="{{ route('reports.documents') }}">{{ __('Document Report') }}</a></li>
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
@canany(['subjects.*', 'bank-accounts.*', 'customers.*', 'customer-groups.*', 'companies.*', 'banks.*', 'management.users.*', 'management.permissions.*',
    'management.roles.*', 'management.configs.*'])
    <li class="dropdown dropdown-hover">
        <div tabindex="3" role="button">{{ __('Management') }}</div>
        <ul tabindex="3" class="dropdown-content menu bg-base-100 rounded-box z-[1] w-52 p-2 shadow">
            @can('subjects.*')
                <li><a href="{{ route('subjects.index') }}">{{ __('Subjects') }}</a></li>
            @endcan
            @can('bank-accounts.*')
                <li><a href="{{ route('bank-accounts.index') }}">{{ __('Bank Accounts') }}</a></li>
            @endcan
            @can('customers.*')
                <li><a href="{{ route('customers.index') }}">{{ __('Customers') }} </a></li>
            @endcan
            @can('customer-groups.*')
                <li><a href="{{ route('customer-groups.index') }}">{{ __('Customer Groups') }} </a></li>
            @endcan
            @can('companies.*')
                <li><a href="{{ route('companies.index') }}">{{ __('Companies') }} </a></li>
            @endcan
            @can('banks.*')
                <li><a href="{{ route('banks.index') }}">{{ __('Banks') }}</a></li>
            @endcan
            @can('management.users.*')
                <li><a href="{{ route('users.index') }}">{{ __('Users') }}</a></li>
            @endcan
            @can('management.permissions.*')
                <li><a href="{{ route('permissions.index') }}">{{ __('Permissions') }}</a></li>
            @endcan
            @can('management.roles.*')
                <li><a href="{{ route('roles.index') }}">{{ __('Roles') }}</a></li>
            @endcan
            @can('management.configs.*')
                <li><a href="{{ route('configs.index') }}">{{ __('Configs') }}</a></li>
            @endcan
            <li><a href="https://github.com/Jooyeshgar/FreeAmir/issues">{{ __('Support') }}</a></li>
        </ul>
    </li>
@endcanany
