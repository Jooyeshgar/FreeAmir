@php
    $availableQuickLinks = collect([
        ['permission' => 'invoices.index', 'area' => 'sell-invoices-link', 'label' => __('Sell Invoices'), 'href' => route('invoices.index', ['invoice_type' => 'sell']), 'style' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 hover:border-emerald-500/50 hover:bg-emerald-500/15 dark:border-emerald-400/40 dark:bg-emerald-950/45 dark:text-emerald-300 dark:hover:border-emerald-300/55 dark:hover:bg-emerald-900/55'],
        ['permission' => 'invoices.index', 'area' => 'buy-invoices-link', 'label' => __('Buy Invoices'), 'href' => route('invoices.index', ['invoice_type' => 'buy']), 'style' => 'border-sky-500/30 bg-sky-500/10 text-sky-700 hover:border-sky-500/50 hover:bg-sky-500/15 dark:border-sky-400/40 dark:bg-sky-950/45 dark:text-sky-300 dark:hover:border-sky-300/55 dark:hover:bg-sky-900/55'],
        ['permission' => 'invoices.create', 'area' => 'create-sell-invoice-link', 'label' => __('Create Sell Invoice'), 'href' => route('invoices.create', ['invoice_type' => 'sell']), 'style' => 'border-blue-500/30 bg-blue-500/10 text-blue-700 hover:border-blue-500/50 hover:bg-blue-500/15 dark:border-blue-400/40 dark:bg-blue-950/45 dark:text-blue-300 dark:hover:border-blue-300/55 dark:hover:bg-blue-900/55'],
        ['permission' => 'customers.create', 'area' => 'create-customer-link', 'label' => __('Create Customer'), 'href' => route('customers.create'), 'style' => 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-700 hover:border-fuchsia-500/50 hover:bg-fuchsia-500/15 dark:border-fuchsia-400/40 dark:bg-fuchsia-950/45 dark:text-fuchsia-300 dark:hover:border-fuchsia-300/55 dark:hover:bg-fuchsia-900/55'],
        ['permission' => 'crm.dashboard', 'area' => 'crm-dashboard', 'label' => __('CRM Dashboard'), 'href' => route('crm.dashboard'), 'style' => 'border-rose-500/30 bg-rose-500/10 text-rose-700 hover:border-rose-500/50 hover:bg-rose-500/15 dark:border-rose-400/40 dark:bg-rose-950/45 dark:text-rose-300 dark:hover:border-rose-300/55 dark:hover:bg-rose-900/55'],
        ['permission' => 'ancillary-costs.index', 'area' => 'ancillary-costs', 'label' => __('Ancillary Costs'), 'href' => route('ancillary-costs.index'), 'style' => 'border-slate-500/30 bg-slate-500/10 text-slate-700 hover:border-slate-500/50 hover:bg-slate-500/15 dark:border-slate-400/40 dark:bg-slate-950/45 dark:text-slate-300 dark:hover:border-slate-300/55 dark:hover:bg-slate-900/55'],
        ['permission' => 'services.create', 'area' => 'create-service-link', 'label' => __('Add Service'), 'href' => route('services.create'), 'style' => 'border-orange-500/30 bg-orange-500/10 text-orange-700 hover:border-orange-500/50 hover:bg-orange-500/15 dark:border-orange-400/40 dark:bg-orange-950/45 dark:text-orange-300 dark:hover:border-orange-300/55 dark:hover:bg-orange-900/55'],
        ['permission' => 'products.create', 'area' => 'create-product-link', 'label' => __('Create Product'), 'href' => route('products.create'), 'style' => 'border-lime-500/30 bg-lime-500/10 text-lime-700 hover:border-lime-500/50 hover:bg-lime-500/15 dark:border-lime-400/40 dark:bg-lime-950/45 dark:text-lime-300 dark:hover:border-lime-300/55 dark:hover:bg-lime-900/55'],
        ['permission' => 'warehouse.dashboard', 'area' => 'warehouse-dashboard', 'label' => __('Warehouse Dashboard'), 'href' => route('warehouse.dashboard'), 'style' => 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 hover:border-indigo-500/50 hover:bg-indigo-500/15 dark:border-indigo-400/40 dark:bg-indigo-950/45 dark:text-indigo-300 dark:hover:border-indigo-300/55 dark:hover:bg-indigo-900/55'],
        ['permission' => 'products.report', 'area' => 'inventory-report', 'label' => __('Warehouse Report'), 'href' => route('products.report'), 'style' => 'border-teal-500/30 bg-teal-500/10 text-teal-700 hover:border-teal-500/50 hover:bg-teal-500/15 dark:border-teal-400/40 dark:bg-teal-950/45 dark:text-teal-300 dark:hover:border-teal-300/55 dark:hover:bg-teal-900/55'],
        ['permission' => 'products.import', 'area' => 'import-products-link', 'label' => __('Import Products'), 'href' => route('products.import'), 'style' => 'border-red-500/30 bg-red-500/10 text-red-700 hover:border-red-500/50 hover:bg-red-500/15 dark:border-red-400/40 dark:bg-red-950/45 dark:text-red-300 dark:hover:border-red-300/55 dark:hover:bg-red-900/55'],
        ['permission' => 'products.export', 'area' => 'export-products-link', 'label' => __('Receive Products Report'), 'href' => route('products.export'), 'style' => 'border-zinc-500/30 bg-zinc-500/10 text-zinc-700 hover:border-zinc-500/50 hover:bg-zinc-500/15 dark:border-zinc-400/40 dark:bg-zinc-950/45 dark:text-zinc-300 dark:hover:border-zinc-300/55 dark:hover:bg-zinc-900/55'],
        ['permission' => 'employee-portal.dashboard', 'area' => 'employee-overview', 'label' => __('My Portal'), 'href' => route('employee-portal.dashboard'), 'style' => 'border-pink-500/30 bg-pink-500/10 text-pink-700 hover:border-pink-500/50 hover:bg-pink-500/15 dark:border-pink-400/40 dark:bg-pink-950/45 dark:text-pink-300 dark:hover:border-pink-300/55 dark:hover:bg-pink-900/55'],
        ['permission' => 'employee-portal.employee.show', 'area' => 'employee-profile', 'label' => __('Employment Profile'), 'href' => route('employee-portal.employee.show'), 'style' => 'border-green-500/30 bg-green-500/10 text-green-700 hover:border-green-500/50 hover:bg-green-500/15 dark:border-green-400/40 dark:bg-green-950/45 dark:text-green-300 dark:hover:border-green-300/55 dark:hover:bg-green-900/55'],
        ['permission' => 'employee-portal.attendance-logs', 'area' => 'employee-attendance', 'label' => __('Attendance'), 'href' => route('employee-portal.attendance-logs'), 'style' => 'border-yellow-500/30 bg-yellow-500/10 text-yellow-700 hover:border-yellow-500/50 hover:bg-yellow-500/15 dark:border-yellow-400/40 dark:bg-yellow-950/45 dark:text-yellow-300 dark:hover:border-yellow-300/55 dark:hover:bg-yellow-900/55'],
        ['permission' => 'employee-portal.monthly-attendances', 'area' => 'employee-monthly-attendance', 'label' => __('Monthly Attendance'), 'href' => route('employee-portal.monthly-attendances'), 'style' => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 hover:border-cyan-500/50 hover:bg-cyan-500/15 dark:border-cyan-400/40 dark:bg-cyan-950/45 dark:text-cyan-300 dark:hover:border-cyan-300/55 dark:hover:bg-cyan-900/55'],
        ['permission' => 'employee-portal.payrolls', 'area' => 'employee-payroll', 'label' => __('Payroll'), 'href' => route('employee-portal.payrolls'), 'style' => 'border-stone-500/30 bg-stone-500/10 text-stone-700 hover:border-stone-500/50 hover:bg-stone-500/15 dark:border-stone-400/40 dark:bg-stone-950/45 dark:text-stone-300 dark:hover:border-stone-300/55 dark:hover:bg-stone-900/55'],
        ['permission' => 'employee-portal.personnel-requests.index', 'area' => 'employee-requests', 'label' => __('Personnel Requests'), 'href' => route('employee-portal.personnel-requests.index'), 'style' => 'border-neutral-500/30 bg-neutral-500/10 text-neutral-700 hover:border-neutral-500/50 hover:bg-neutral-500/15 dark:border-neutral-400/40 dark:bg-neutral-950/45 dark:text-neutral-300 dark:hover:border-neutral-300/55 dark:hover:bg-neutral-900/55'],
    ])->filter(fn (array $link) => auth()->user()->can($link['permission']))->values();

    $quickLinks = $availableQuickLinks->take(12);
    $standaloneLinks = collect([
        'create-product-link',
        'warehouse-dashboard',
        'create-service-link',
        'ancillary-costs',
        'create-customer-link',
        'crm-dashboard',
    ])->map(fn (string $area) => $availableQuickLinks->firstWhere('area', $area))->filter();

    $businessGridClass = match (true) {
        $canSales && $standaloneLinks->isNotEmpty() => 'md:grid-cols-2 xl:grid-cols-5',
        $canSales => 'md:grid-cols-2 xl:grid-cols-4',
        $standaloneLinks->isNotEmpty() => 'xl:grid-cols-3',
        default => 'lg:max-w-2xl',
    };
    $businessCardClass = ($canSales || $standaloneLinks->isNotEmpty()) ? 'xl:col-span-2' : '';
@endphp

@if (! $canFinancial && $quickLinks->isNotEmpty())
    <nav aria-label="{{ __('Quick Access') }}">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ($quickLinks as $link)
                @include('home._quick-access-link', compact('link'))
            @endforeach
        </div>
    </nav>
@elseif ($hasBusinessPerms && $canFinancial)
    <section aria-labelledby="work-areas-title">
        <div class="grid grid-cols-1 gap-4 {{ $businessGridClass }}">
            @if ($canFinancial)
                <article data-home-area="accounting" @if (in_array($homeVariant, ['accounting', 'admin'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/25 hover:shadow-lg {{ $businessCardClass }}">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-primary"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-primary/10 p-2.5 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 2h9l4 4v16H6V2Zm9 0v5h4M9 12h7M9 16h7" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-bold text-base-content">{{ __('Accounting') }}</h3>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Documents, cheques and bank accounts') }}</p>
                        </div>
                    </div>

                    @include('home._latest-quick-access-data', ['area' => 'accounting', 'items' => $quickAccessRecentData['accounting']])

                    <div class="mt-4 flex gap-1">
                        @can('documents.index')
                            <a href="{{ route('documents.index') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-primary/15 hover:bg-primary/5">
                                <span class="truncate">{{ __('Documents') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('cheques.index')
                            <a href="{{ route('cheques.index') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-primary/15 hover:bg-primary/5">
                                <span class="truncate">{{ __('Cheque Management') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('bank-accounts.index')
                            <a href="{{ route('bank-accounts.index') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-primary/15 hover:bg-primary/5">
                                <span class="truncate">{{ __('Bank Accounts') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>
                </article>
            @endif

            @if ($canSales)
                <article data-home-area="sales" @if (in_array($homeVariant, ['sales', 'operations'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-success/25 hover:shadow-lg {{ $businessCardClass }}">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-success"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-success/10 p-2.5 text-success">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5h2l2 11h10l2-8H6m3 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-bold text-base-content">{{ __('Invoices') }}</h3>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Invoices, add customer') }}</p>
                        </div>
                    </div>

                    @include('home._latest-quick-access-data', ['area' => 'sales', 'items' => $quickAccessRecentData['sales']])

                    <div class="mt-4 flex gap-1">
                        <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-success/15 hover:bg-success/5">
                            <span class="truncate">{{ __('Sell Invoices') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        <a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-success/15 hover:bg-success/5">
                            <span class="truncate">{{ __('Buy Invoices') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        @can('customers.create')
                            <a href="{{ route('customers.create') }}" class="flex min-w-0 flex-1 items-center justify-between gap-1 rounded-xl border border-transparent bg-base-100/60 px-2 py-2 text-xs transition hover:border-success/15 hover:bg-success/5">
                                <span class="truncate">{{ __('Add Customer') }}</span><span class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>
                </article>
            @endif

            @if ($standaloneLinks->isNotEmpty())
                <aside class="grid gap-2 xl:auto-rows-fr" aria-label="{{ __('Quick Access') }}">
                    @foreach ($standaloneLinks as $link)
                        @include('home._quick-access-link', compact('link'))
                    @endforeach
                </aside>
            @endif
        </div>
    </section>
@endif
