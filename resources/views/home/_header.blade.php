<section data-home-variant="{{ $homeVariant }}" class="relative isolate overflow-hidden rounded-3xl border p-6 shadow-sm sm:p-8 border-info/25 bg-gradient-to-l from-info/10 via-base-100 to-base-100">
    <div aria-hidden="true" class="absolute -end-20 -top-24 size-64 rounded-full blur-3xl bg-info/20"></div>
    <div aria-hidden="true" class="absolute -bottom-24 start-1/3 size-48 rounded-full bg-base-200/50 blur-3xl"></div>

    <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem] lg:items-center">
        <div class="flex min-w-0 items-start gap-4 sm:gap-5">
            <span class="inline-flex size-14 shrink-0 items-center justify-center rounded-2xl shadow-sm ring-1 ring-current/10 sm:size-16 bg-info/15 text-info">
                @switch($homeVariant)
                    @case('platform')
                    @case('admin')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3 4 7v5c0 4.7 3.2 7.7 8 9 4.8-1.3 8-4.3 8-9V7l-8-4Zm-3 9 2 2 4-4" />
                        </svg>
                        @break
                    @case('accounting')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 2h9l4 4v16H6V2Zm9 0v5h4M9 12h7M9 16h7" />
                        </svg>
                        @break
                    @case('sales')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5h2l2 11h10l2-8H6m3 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                        </svg>
                        @break
                    @case('inventory')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10" />
                        </svg>
                        @break
                    @case('services')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h10M4 12h16M4 17h10m5-12 2 2-2 2m0 6 2 2-2 2" />
                        </svg>
                        @break
                    @case('crm')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-2v6m3-3h-6" />
                        </svg>
                        @break
                    @case('operations')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h11M4 12h16M9 17h11M15 4l3 3-3 3M9 14l-3 3 3 3" />
                        </svg>
                        @break
                    @case('employee')
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 9a7 7 0 0 0-14 0" />
                        </svg>
                        @break
                    @default
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-7 sm:size-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                @endswitch
            </span>

            <div class="min-w-0 pt-0.5">
                <h1 class="mt-3 text-2xl font-black tracking-tight text-base-content sm:text-3xl">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</h1>
                @php
                    $homeProfilesDesc = [
                        'platform' => __('Move between management and the active company workspace.'),
                        'accounting' => __('Continue with documents, accounts, and financial reports.'),
                        'sales' => __('Continue with customers, sales invoices, and CRM.'),
                        'inventory' => __('Continue with stock, products, and warehouse operations.'),
                        'services' => __('Continue with services and service groups.'),
                        'crm' => __('Continue with customers and CRM activities.'),
                        'operations' => __('Work across sales and warehouse tasks from one focused page.'),
                        'employee' => __('Open your attendance, payroll, and personnel requests.'),
                        'business' => __('Choose a task and continue in the dedicated workspace.'),
                    ];
                    $homeProfileDesc = $homeProfilesDesc[$homeVariant] ?? $homeProfilesDesc['business'];
                @endphp
                <p class="mt-2 max-w-2xl text-sm leading-6 text-base-content/60">{{ $homeProfileDesc }}</p>
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-base-content/50">
                    <span class="inline-flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" />
                        </svg>
                        {{ __('Today') }}: {{ formatDate(now()) }}
                    </span>
                </div>
            </div>
        </div>
        @php
            $primaryAction = match ($homeVariant) {
                'platform' => auth()->user()->can('access-super-admin-panel')
                    ? [
                        'label' => __('Admin panel'),
                        'description' => __('Manage Free Amir in management panel.'),
                        'href' => route('management.dashboard'),
                    ]
                    : null,
                'admin', 'accounting' => auth()->user()->can('documents.create')
                    ? [
                        'label' => __('Create Document'),
                        'description' => __('Create a new document.'),
                        'href' => route('documents.create'),
                    ]
                    : (auth()->user()->can('documents.index')
                        ? [
                            'label' => __('Documents'),
                            'description' => __('Review and manage accounting documents.'),
                            'href' => route('documents.index'),
                        ]
                        : null),
                'sales', 'operations' => auth()->user()->can('invoices.create')
                    ? [
                        'label' => __('Create Sell Invoice'),
                        'description' => __('Create a new sell invoice.'),
                        'href' => route('invoices.create', ['invoice_type' => 'sell']),
                    ]
                    : (auth()->user()->can('crm.dashboard')
                        ? [
                            'label' => __('CRM Dashboard'),
                            'description' => __('Review customers and sales activity.'),
                            'href' => route('crm.dashboard'),
                        ]
                        : [
                            'label' => __('Invoices'),
                            'description' => __('Review and manage sales invoices.'),
                            'href' => route('invoices.index'),
                        ]),
                'inventory' => auth()->user()->can('warehouse.dashboard')
                    ? [
                        'label' => __('Warehouse Dashboard'),
                        'description' => __('Monitor stock and warehouse operations.'),
                        'href' => route('warehouse.dashboard'),
                    ]
                    : [
                        'label' => __('Products'),
                        'description' => __('Review and manage products and inventory.'),
                        'href' => route('products.index'),
                    ],
                'services' => auth()->user()->can('services.create')
                    ? [
                        'label' => __('Add Service'),
                        'description' => __('Create a new service.'),
                        'href' => route('services.create'),
                    ]
                    : [
                        'label' => __('Services'),
                        'description' => __('Review and manage services.'),
                        'href' => route('services.index'),
                    ],
                'crm' => auth()->user()->can('customers.create')
                    ? [
                        'label' => __('Add Customer'),
                        'description' => __('Create a new customer.'),
                        'href' => route('customers.create'),
                    ]
                    : [
                        'label' => __('Customers'),
                        'description' => __('Review and manage customers.'),
                        'href' => route('customers.index'),
                    ],
                'employee' => auth()->user()->can('employee-portal.dashboard')
                    ? [
                        'label' => __('Employee Portal'),
                        'description' => __('View your attendance, payroll, and personnel requests.'),
                        'href' => route('employee-portal.dashboard'),
                    ]
                    : null,
                default => null,
            };
        @endphp

        @if ($primaryAction)
            <aside class="rounded-2xl border border-base-content/10 bg-base-100/70 p-4 shadow-sm backdrop-blur-sm">
                <div class="flex items-center gap-1">
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-info/15 text-info">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                    <p class="text-sm font-bold text-base-content">
                        {{ $primaryAction['description'] }}
                    </p>
                </div>
                <a href="{{ $primaryAction['href'] }}" class="btn btn-sm mt-4 w-full btn-info">
                    {{ $primaryAction['label'] }}
                    <span aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                </a>
            </aside>
        @endif
    </div>
</section>
