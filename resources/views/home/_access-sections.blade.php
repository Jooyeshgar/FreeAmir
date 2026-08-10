@if ($usesDirectQuickLinks)
    <nav aria-label="{{ __('Quick Access') }}">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            @foreach ($quickLinks as $link)
                @include('home._quick-access-link', compact('link'))
            @endforeach
        </div>
    </nav>
@elseif ($hasBusinessPerms && $businessAreaCount > 0)
    <section aria-labelledby="work-areas-title">
        <div class="mb-4 flex items-center gap-3">
            <span class="inline-flex size-10 items-center justify-center rounded-xl bg-base-200 text-base-content/60">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z" />
                </svg>
            </span>
            <div>
                <h2 id="work-areas-title" class="text-base font-bold text-base-content">{{ __('Quick Access') }}</h2>
                <p class="mt-0.5 text-xs text-base-content/55">{{ __('Open the area you need.') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 {{ $businessGridClass }}">
            @if ($canFinancial)
                <article data-home-area="accounting" @if (in_array($homeVariant, ['accounting', 'admin'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/25 hover:shadow-lg">
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

                    <div class="mt-5 flex-1 space-y-2">
                        @can('documents.index')
                            <a href="{{ route('documents.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-primary/15 hover:bg-primary/5">
                                <span>{{ __('Documents') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('cheques.index')
                            <a href="{{ route('cheques.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-primary/15 hover:bg-primary/5">
                                <span>{{ __('Cheque Management') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('bank-accounts.index')
                            <a href="{{ route('bank-accounts.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-primary/15 hover:bg-primary/5">
                                <span>{{ __('Bank Accounts') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>

                    @can('documents.create')
                        <a href="{{ route('documents.create') }}" class="btn btn-sm btn-primary mt-4 w-full">{{ __('Create Document') }}</a>
                    @endcan
                </article>
            @endif

            @if ($canSales)
                <article data-home-area="sales" @if (in_array($homeVariant, ['sales', 'operations'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-success/25 hover:shadow-lg">
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

                    <div class="mt-5 flex-1 space-y-2">
                        <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-success/15 hover:bg-success/5">
                            <span>{{ __('Sell Invoices') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        <a href="{{ route('invoices.index', ['invoice_type' => 'buy']) }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-success/15 hover:bg-success/5">
                            <span>{{ __('Buy Invoices') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        @can('customers.create')
                            <a href="{{ route('customers.create') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-success/15 hover:bg-success/5">
                                <span>{{ __('Add Customer') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-success/10 text-success" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>

                    @can('invoices.create')
                        <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-sm btn-success mt-4 w-full">{{ __('Create Sell Invoice') }}</a>
                    @endcan
                </article>
            @endif

            @if ($canInventory)
                <article data-home-area="inventory" @if (in_array($homeVariant, ['inventory', 'operations'], true)) data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-info/25 hover:shadow-lg">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-info"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-info/10 p-2.5 text-info">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7M12 11v10" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="font-bold text-base-content">{{ __('Inventory') }}</h3>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Stock and product management') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex-1 space-y-2">
                        <a href="{{ route('products.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-info/15 hover:bg-info/5">
                            <span>{{ __('Products') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-info/10 text-info" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        @can('product-groups.index')
                            <a href="{{ route('product-groups.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-info/15 hover:bg-info/5">
                                <span>{{ __('Product Groups') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-info/10 text-info" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('warehouse.dashboard')
                            <a href="{{ route('warehouse.dashboard') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-info/15 hover:bg-info/5">
                                <span>{{ __('Warehouse Dashboard') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-info/10 text-info" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>

                    @can('products.create')
                        <a href="{{ route('products.create') }}" class="btn btn-sm btn-info mt-4 w-full">{{ __('Create Product') }}</a>
                    @endcan
                </article>
            @endif

            @if ($canServices)
                <article data-home-area="services" @if ($homeVariant === 'services') data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-warning/25 hover:shadow-lg">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-warning"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-warning/10 p-2.5 text-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h10M4 12h16M4 17h10m5-12 2 2-2 2m0 6 2 2-2 2" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-base-content">{{ __('Services') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Service and group management') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex-1 space-y-2">
                        <a href="{{ route('services.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-warning/15 hover:bg-warning/5">
                            <span>{{ __('Services') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-warning/10 text-warning" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        @can('service-groups.index')
                            <a href="{{ route('service-groups.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-warning/15 hover:bg-warning/5">
                                <span>{{ __('Service Groups') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-warning/10 text-warning" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('ancillary-costs.index')
                            <a href="{{ route('ancillary-costs.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-warning/15 hover:bg-warning/5">
                                <span>{{ __('Ancillary Costs') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-warning/10 text-warning" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>

                    @can('services.create')
                        <a href="{{ route('services.create') }}" class="btn btn-sm btn-warning mt-4 w-full">{{ __('Create Service') }}</a>
                    @endcan
                </article>
            @endif

            @if ($canCustomers)
                <article data-home-area="crm" @if ($homeVariant === 'crm') data-home-featured="true" @endif
                    class="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-secondary/25 hover:shadow-lg">
                    <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-secondary"></div>
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-secondary/10 p-2.5 text-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-2v6m3-3h-6" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-bold text-base-content">{{ __('Customers') }}</h3>
                            <p class="mt-1 text-xs leading-5 text-base-content/55">{{ __('Customer and CRM management') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 flex-1 space-y-2">
                        <a href="{{ route('customers.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-secondary/15 hover:bg-secondary/5">
                            <span>{{ __('Customers') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-secondary/10 text-secondary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                        </a>
                        @can('customer-groups.index')
                            <a href="{{ route('customer-groups.index') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-secondary/15 hover:bg-secondary/5">
                                <span>{{ __('Customer Groups') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-secondary/10 text-secondary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                        @can('crm.dashboard')
                            <a href="{{ route('crm.dashboard') }}" class="flex items-center justify-between rounded-xl border border-transparent bg-base-100/60 px-3 py-2 text-sm transition hover:border-secondary/15 hover:bg-secondary/5">
                                <span>{{ __('CRM Dashboard') }}</span><span class="inline-flex size-7 items-center justify-center rounded-lg bg-secondary/10 text-secondary" aria-hidden="true">{{ app()->isLocale('fa') ? '←' : '→' }}</span>
                            </a>
                        @endcan
                    </div>

                    @can('customers.create')
                        <a href="{{ route('customers.create') }}" class="btn btn-sm btn-secondary mt-4 w-full">{{ __('Create Customer') }}</a>
                    @endcan
                </article>
            @endif

        </div>
    </section>
@endif
