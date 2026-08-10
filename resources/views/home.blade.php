<x-app-layout :title="__('Home')">
    <x-show-message-bags />

    @php
        $homeProfiles = [
            'platform' => [
                'description' => __('Move between management and the active company workspace.'),
                'panel' => 'border-info/25 bg-gradient-to-l from-info/10 via-base-100 to-base-100',
                'icon' => 'bg-info/15 text-info',
                'eyebrow' => 'text-info',
                'glow' => 'bg-info/20',
                'button' => 'btn-info',
            ],
            'accounting' => [
                'description' => __('Continue with documents, accounts, and financial reports.'),
                'panel' => 'border-info/25 bg-gradient-to-l from-info/10 via-base-100 to-base-100',
                'icon' => 'bg-info/15 text-info',
                'eyebrow' => 'text-info',
                'glow' => 'bg-info/20',
                'button' => 'btn-info',
            ],
            'sales' => [
                'description' => __('Continue with customers, sales invoices, and CRM.'),
                'panel' => 'border-success/25 bg-gradient-to-l from-success/10 via-base-100 to-base-100',
                'icon' => 'bg-success/15 text-success',
                'eyebrow' => 'text-success',
                'glow' => 'bg-success/20',
                'button' => 'btn-success',
            ],
            'inventory' => [
                'description' => __('Continue with stock, products, and warehouse operations.'),
                'panel' => 'border-info/25 bg-gradient-to-l from-info/10 via-base-100 to-base-100',
                'icon' => 'bg-info/15 text-info',
                'eyebrow' => 'text-info',
                'glow' => 'bg-info/20',
                'button' => 'btn-info',
            ],
            'services' => [
                'description' => __('Continue with services and service groups.'),
                'panel' => 'border-warning/25 bg-gradient-to-l from-warning/10 via-base-100 to-base-100',
                'icon' => 'bg-warning/15 text-warning',
                'eyebrow' => 'text-warning',
                'glow' => 'bg-warning/20',
                'button' => 'btn-warning',
            ],
            'crm' => [
                'description' => __('Continue with customers and CRM activities.'),
                'panel' => 'border-secondary/25 bg-gradient-to-l from-secondary/10 via-base-100 to-base-100',
                'icon' => 'bg-secondary/15 text-secondary',
                'eyebrow' => 'text-secondary',
                'glow' => 'bg-secondary/20',
                'button' => 'btn-secondary',
            ],
            'operations' => [
                'description' => __('Work across sales and warehouse tasks from one focused page.'),
                'panel' => 'border-accent/30 bg-gradient-to-l from-accent/10 via-base-100 to-base-100',
                'icon' => 'bg-accent/15 text-accent',
                'eyebrow' => 'text-accent',
                'glow' => 'bg-accent/20',
                'button' => 'btn-accent',
            ],
            'employee' => [
                'description' => __('Open your attendance, payroll, and personnel requests.'),
                'panel' => 'border-success/25 bg-gradient-to-l from-success/10 via-base-100 to-base-100',
                'icon' => 'bg-success/15 text-success',
                'eyebrow' => 'text-success',
                'glow' => 'bg-success/20',
                'button' => 'btn-success',
            ],
            'business' => [
                'description' => __('Choose a task and continue in the dedicated workspace.'),
                'panel' => 'border-base-300 bg-base-100',
                'icon' => 'bg-base-200 text-base-content',
                'eyebrow' => 'text-base-content/60',
                'glow' => 'bg-base-300/40',
                'button' => 'btn-neutral',
            ],
        ];

        $homeProfile = $homeProfiles[$homeVariant] ?? $homeProfiles['business'];
        $currentUser = auth()->user();
        $quickLinks = collect([
            ['permission' => 'invoices.index', 'area' => 'sell-invoices-link', 'label' => __('Sell Invoices'), 'href' => route('invoices.index', ['invoice_type' => 'sell']), 'style' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 hover:border-emerald-500/50 hover:bg-emerald-500/15 dark:border-emerald-400/40 dark:bg-emerald-950/45 dark:text-emerald-300 dark:hover:border-emerald-300/55 dark:hover:bg-emerald-900/55'],
            ['permission' => 'invoices.index', 'area' => 'buy-invoices-link', 'label' => __('Buy Invoices'), 'href' => route('invoices.index', ['invoice_type' => 'buy']), 'style' => 'border-sky-500/30 bg-sky-500/10 text-sky-700 hover:border-sky-500/50 hover:bg-sky-500/15 dark:border-sky-400/40 dark:bg-sky-950/45 dark:text-sky-300 dark:hover:border-sky-300/55 dark:hover:bg-sky-900/55'],
            ['permission' => 'invoices.create', 'area' => 'create-sell-invoice-link', 'label' => __('Create Sell Invoice'), 'href' => route('invoices.create', ['invoice_type' => 'sell']), 'style' => 'border-blue-500/30 bg-blue-500/10 text-blue-700 hover:border-blue-500/50 hover:bg-blue-500/15 dark:border-blue-400/40 dark:bg-blue-950/45 dark:text-blue-300 dark:hover:border-blue-300/55 dark:hover:bg-blue-900/55'],
            ['permission' => 'customers.index', 'area' => 'customers-link', 'label' => __('Customers'), 'href' => route('customers.index'), 'style' => 'border-violet-500/30 bg-violet-500/10 text-violet-700 hover:border-violet-500/50 hover:bg-violet-500/15 dark:border-violet-400/40 dark:bg-violet-950/45 dark:text-violet-300 dark:hover:border-violet-300/55 dark:hover:bg-violet-900/55'],
            ['permission' => 'customers.create', 'area' => 'create-customer-link', 'label' => __('Create Customer'), 'href' => route('customers.create'), 'style' => 'border-fuchsia-500/30 bg-fuchsia-500/10 text-fuchsia-700 hover:border-fuchsia-500/50 hover:bg-fuchsia-500/15 dark:border-fuchsia-400/40 dark:bg-fuchsia-950/45 dark:text-fuchsia-300 dark:hover:border-fuchsia-300/55 dark:hover:bg-fuchsia-900/55'],
            ['permission' => 'customer-groups.index', 'area' => 'customer-groups-link', 'label' => __('Customer Groups'), 'href' => route('customer-groups.index'), 'style' => 'border-amber-500/30 bg-amber-500/10 text-amber-700 hover:border-amber-500/50 hover:bg-amber-500/15 dark:border-amber-400/40 dark:bg-amber-950/45 dark:text-amber-300 dark:hover:border-amber-300/55 dark:hover:bg-amber-900/55'],
            ['permission' => 'crm.dashboard', 'area' => 'crm-dashboard', 'label' => __('CRM Dashboard'), 'href' => route('crm.dashboard'), 'style' => 'border-rose-500/30 bg-rose-500/10 text-rose-700 hover:border-rose-500/50 hover:bg-rose-500/15 dark:border-rose-400/40 dark:bg-rose-950/45 dark:text-rose-300 dark:hover:border-rose-300/55 dark:hover:bg-rose-900/55'],
            ['permission' => 'ancillary-costs.index', 'area' => 'ancillary-costs', 'label' => __('Ancillary Costs'), 'href' => route('ancillary-costs.index'), 'style' => 'border-slate-500/30 bg-slate-500/10 text-slate-700 hover:border-slate-500/50 hover:bg-slate-500/15 dark:border-slate-400/40 dark:bg-slate-950/45 dark:text-slate-300 dark:hover:border-slate-300/55 dark:hover:bg-slate-900/55'],
            ['permission' => 'products.index', 'area' => 'products-link', 'label' => __('Products'), 'href' => route('products.index'), 'style' => 'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 hover:border-cyan-500/50 hover:bg-cyan-500/15 dark:border-cyan-400/40 dark:bg-cyan-950/45 dark:text-cyan-300 dark:hover:border-cyan-300/55 dark:hover:bg-cyan-900/55'],
            ['permission' => 'products.create', 'area' => 'create-product-link', 'label' => __('Create Product'), 'href' => route('products.create'), 'style' => 'border-lime-500/30 bg-lime-500/10 text-lime-700 hover:border-lime-500/50 hover:bg-lime-500/15 dark:border-lime-400/40 dark:bg-lime-950/45 dark:text-lime-300 dark:hover:border-lime-300/55 dark:hover:bg-lime-900/55'],
            ['permission' => 'warehouse.dashboard', 'area' => 'warehouse-dashboard', 'label' => __('Warehouse Dashboard'), 'href' => route('warehouse.dashboard'), 'style' => 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 hover:border-indigo-500/50 hover:bg-indigo-500/15 dark:border-indigo-400/40 dark:bg-indigo-950/45 dark:text-indigo-300 dark:hover:border-indigo-300/55 dark:hover:bg-indigo-900/55'],
            ['permission' => 'product-groups.index', 'area' => 'product-groups', 'label' => __('Product Groups'), 'href' => route('product-groups.index'), 'style' => 'border-purple-500/30 bg-purple-500/10 text-purple-700 hover:border-purple-500/50 hover:bg-purple-500/15 dark:border-purple-400/40 dark:bg-purple-950/45 dark:text-purple-300 dark:hover:border-purple-300/55 dark:hover:bg-purple-900/55'],
            ['permission' => 'products.report', 'area' => 'inventory-report', 'label' => __('Inventory report'), 'href' => route('products.report'), 'style' => 'border-teal-500/30 bg-teal-500/10 text-teal-700 hover:border-teal-500/50 hover:bg-teal-500/15 dark:border-teal-400/40 dark:bg-teal-950/45 dark:text-teal-300 dark:hover:border-teal-300/55 dark:hover:bg-teal-900/55'],
            ['permission' => 'product-groups.create', 'area' => 'create-product-group-link', 'label' => __('Create Product Group'), 'href' => route('product-groups.create'), 'style' => 'border-orange-500/30 bg-orange-500/10 text-orange-700 hover:border-orange-500/50 hover:bg-orange-500/15 dark:border-orange-400/40 dark:bg-orange-950/45 dark:text-orange-300 dark:hover:border-orange-300/55 dark:hover:bg-orange-900/55'],
            ['permission' => 'products.import', 'area' => 'import-products-link', 'label' => __('Import Products'), 'href' => route('products.import'), 'style' => 'border-red-500/30 bg-red-500/10 text-red-700 hover:border-red-500/50 hover:bg-red-500/15 dark:border-red-400/40 dark:bg-red-950/45 dark:text-red-300 dark:hover:border-red-300/55 dark:hover:bg-red-900/55'],
            ['permission' => 'products.export', 'area' => 'export-products-link', 'label' => __('Export CSV'), 'href' => route('products.export'), 'style' => 'border-zinc-500/30 bg-zinc-500/10 text-zinc-700 hover:border-zinc-500/50 hover:bg-zinc-500/15 dark:border-zinc-400/40 dark:bg-zinc-950/45 dark:text-zinc-300 dark:hover:border-zinc-300/55 dark:hover:bg-zinc-900/55'],
            ['permission' => 'employee-portal.dashboard', 'area' => 'employee-overview', 'label' => __('Employee Portal'), 'href' => route('employee-portal.dashboard'), 'style' => 'border-pink-500/30 bg-pink-500/10 text-pink-700 hover:border-pink-500/50 hover:bg-pink-500/15 dark:border-pink-400/40 dark:bg-pink-950/45 dark:text-pink-300 dark:hover:border-pink-300/55 dark:hover:bg-pink-900/55'],
            ['permission' => 'employee-portal.employee.show', 'area' => 'employee-profile', 'label' => __('Employment Profile'), 'href' => route('employee-portal.employee.show'), 'style' => 'border-green-500/30 bg-green-500/10 text-green-700 hover:border-green-500/50 hover:bg-green-500/15 dark:border-green-400/40 dark:bg-green-950/45 dark:text-green-300 dark:hover:border-green-300/55 dark:hover:bg-green-900/55'],
            ['permission' => 'employee-portal.attendance-logs', 'area' => 'employee-attendance', 'label' => __('Attendance'), 'href' => route('employee-portal.attendance-logs'), 'style' => 'border-yellow-500/30 bg-yellow-500/10 text-yellow-700 hover:border-yellow-500/50 hover:bg-yellow-500/15 dark:border-yellow-400/40 dark:bg-yellow-950/45 dark:text-yellow-300 dark:hover:border-yellow-300/55 dark:hover:bg-yellow-900/55'],
            ['permission' => 'employee-portal.payrolls', 'area' => 'employee-payroll', 'label' => __('Payroll'), 'href' => route('employee-portal.payrolls'), 'style' => 'border-stone-500/30 bg-stone-500/10 text-stone-700 hover:border-stone-500/50 hover:bg-stone-500/15 dark:border-stone-400/40 dark:bg-stone-950/45 dark:text-stone-300 dark:hover:border-stone-300/55 dark:hover:bg-stone-900/55'],
            ['permission' => 'employee-portal.personnel-requests.index', 'area' => 'employee-requests', 'label' => __('Personnel Requests'), 'href' => route('employee-portal.personnel-requests.index'), 'style' => 'border-neutral-500/30 bg-neutral-500/10 text-neutral-700 hover:border-neutral-500/50 hover:bg-neutral-500/15 dark:border-neutral-400/40 dark:bg-neutral-950/45 dark:text-neutral-300 dark:hover:border-neutral-300/55 dark:hover:bg-neutral-900/55'],
        ])->filter(fn (array $link) => $currentUser->can($link['permission']))->values();

        $privateMetricThemes = [
            'primary' => [
                'card' => 'border-primary/20 bg-gradient-to-br from-primary/10 to-base-100 hover:border-primary/35',
                'icon' => 'bg-primary/10 text-primary',
                'loading' => 'text-primary',
                'button' => 'border-primary/25 text-primary hover:border-primary hover:bg-primary hover:text-primary-content',
            ],
            'secondary' => [
                'card' => 'border-secondary/20 bg-gradient-to-br from-secondary/10 to-base-100 hover:border-secondary/35',
                'icon' => 'bg-secondary/10 text-secondary',
                'loading' => 'text-secondary',
                'button' => 'border-secondary/25 text-secondary hover:border-secondary hover:bg-secondary hover:text-secondary-content',
            ],
            'accent' => [
                'card' => 'border-accent/20 bg-gradient-to-br from-accent/10 to-base-100 hover:border-accent/35',
                'icon' => 'bg-accent/10 text-accent',
                'loading' => 'text-accent',
                'button' => 'border-accent/25 text-accent hover:border-accent hover:bg-accent hover:text-accent-content',
            ],
            'info' => [
                'card' => 'border-info/20 bg-gradient-to-br from-info/10 to-base-100 hover:border-info/35',
                'icon' => 'bg-info/10 text-info',
                'loading' => 'text-info',
                'button' => 'border-info/25 text-info hover:border-info hover:bg-info hover:text-info-content',
            ],
            'success' => [
                'card' => 'border-success/20 bg-gradient-to-br from-success/10 to-base-100 hover:border-success/35',
                'icon' => 'bg-success/10 text-success',
                'loading' => 'text-success',
                'button' => 'border-success/25 text-success hover:border-success hover:bg-success hover:text-success-content',
            ],
            'warning' => [
                'card' => 'border-warning/20 bg-gradient-to-br from-warning/10 to-base-100 hover:border-warning/35',
                'icon' => 'bg-warning/10 text-warning',
                'loading' => 'text-warning',
                'button' => 'border-warning/25 text-warning hover:border-warning hover:bg-warning hover:text-warning-content',
            ],
        ];

        $basePrivateMetrics = collect([
            ['visible' => $canFinancial, 'metric' => 'profit', 'title' => __('Net Profit'), 'description' => __('Current fiscal year'), 'theme' => 'primary', 'iconPath' => 'M4 19V9m5 10V5m5 14v-7m5 7V3'],
            ['visible' => $canFinancial, 'metric' => 'expenses', 'title' => __('Total expenses'), 'description' => __('Current fiscal year'), 'theme' => 'warning', 'iconPath' => 'M4 5h16v14H4V5Zm4 4h8m-8 4h5m4 0h-1'],
            ['visible' => $canSales, 'metric' => 'sales', 'title' => __('Total sales'), 'description' => __('Approved and settled invoices'), 'theme' => 'success', 'iconPath' => 'M3 3v18h18M7 15l4-4 3 3 5-7'],
            ['visible' => $canSales, 'metric' => 'purchases', 'title' => __('Total purchases'), 'description' => __('Approved and settled invoices'), 'theme' => 'secondary', 'iconPath' => 'M3 3v18h18M7 8l4 4 3-3 5 7'],
            ['visible' => $canInventory, 'metric' => 'inventory', 'title' => __('Total Inventory Value'), 'description' => __('Current inventory accounts'), 'theme' => 'info', 'iconPath' => 'm21 8-9-5-9 5 9 5 9-5Zm-18 4 9 5 9-5M3 16l9 5 9-5'],
        ])->filter(fn (array $metric) => $metric['visible'])->values();

        $rolePrivateMetrics = collect(match ($homeVariant) {
            'sales', 'operations' => [
                ['metric' => 'average_sales', 'title' => __('Average sales invoice value'), 'description' => __('Approved and settled sales'), 'theme' => 'accent'],
                ['metric' => 'average_purchases', 'title' => __('Average purchase invoice value'), 'description' => __('Approved and settled purchases'), 'theme' => 'primary'],
            ],
            'inventory' => [
                ['metric' => 'inventory_retail', 'title' => __('Inventory retail value'), 'description' => __('Based on current selling prices'), 'theme' => 'primary'],
                ['metric' => 'inventory_average_cost', 'title' => __('Average product cost'), 'description' => __('Average cost across all products'), 'theme' => 'secondary'],
                ['metric' => 'inventory_average_price', 'title' => __('Average selling price'), 'description' => __('Average selling price across all products'), 'theme' => 'accent'],
            ],
            'employee' => [
                ['metric' => 'employee_net_payment', 'title' => __('Net payment'), 'description' => __('Latest payroll'), 'theme' => 'primary'],
                ['metric' => 'employee_earnings', 'title' => __('Total earnings'), 'description' => __('Latest payroll'), 'theme' => 'secondary'],
                ['metric' => 'employee_deductions', 'title' => __('Total deductions'), 'description' => __('Latest payroll'), 'theme' => 'accent'],
                ['metric' => 'employee_tax', 'title' => __('Income tax'), 'description' => __('Latest payroll'), 'theme' => 'info'],
            ],
            default => [],
        });

        $usesDirectQuickLinks = ! $canFinancial && $quickLinks->isNotEmpty();
        $businessAreaCount = collect([$canFinancial, $canSales, $canInventory, $canServices, $canCustomers])->filter()->count();
        $businessGridClass = match ($businessAreaCount) {
            1 => 'lg:max-w-2xl lg:grid-cols-1',
            2 => 'lg:grid-cols-2',
            3 => 'lg:grid-cols-3',
            4 => 'md:grid-cols-2 xl:grid-cols-4',
            default => 'md:grid-cols-2 xl:grid-cols-5',
        };
        $privateMetricCount = $basePrivateMetrics->count() + $rolePrivateMetrics->count();
        $privateMetricGridClass = $privateMetricCount >= 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4';

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

    <main class="home-dashboard pb-6">
        @include('home._header', compact('homeVariant', 'homeProfile', 'primaryAction'))

        @include('home._financial-values')

        @include('home._access-sections')
    </main>

    @push('scripts')
        <script>
            window.privateHomeMetric = function (url) {
                return {
                    revealed: false,
                    loaded: false,
                    loading: false,
                    error: false,
                    formattedValue: '',
                    unit: '',

                    async toggle() {
                        if (this.revealed) {
                            this.revealed = false;
                            return;
                        }

                        this.revealed = true;
                        if (!this.loaded && !this.loading) {
                            await this.load();
                        }
                    },

                    async load() {
                        this.loading = true;
                        this.error = false;

                        try {
                            const response = await fetch(url, {
                                cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                throw new Error(`Metric request failed with status ${response.status}`);
                            }

                            const data = await response.json();
                            this.formattedValue = data.formattedValue;
                            this.unit = data.unit;
                            this.loaded = true;
                        } catch (error) {
                            this.error = true;
                        } finally {
                            this.loading = false;
                        }
                    },

                    async retry() {
                        await this.load();
                    },

                    reset() {
                        this.revealed = false;
                        this.loaded = false;
                        this.loading = false;
                        this.error = false;
                        this.formattedValue = '';
                        this.unit = '';
                    },
                };
            };

            window.addEventListener('pageshow', (event) => {
                if (event.persisted) {
                    window.dispatchEvent(new CustomEvent('home-private-reset'));
                }
            });
        </script>
    @endpush
</x-app-layout>
