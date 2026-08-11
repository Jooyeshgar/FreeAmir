@can('home.summary')
    @if (($hasBusinessPerms && ($canFinancial || $canSales || $canInventory)) || ($canSeePersonalPortal && $hasPersonalData))
        <section aria-labelledby="private-summary-title" class="relative overflow-hidden rounded-3xl border border-base-300 bg-base-100 p-5 shadow-sm sm:p-6">
            <div aria-hidden="true" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary via-success to-info"></div>
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="private-summary-title" class="text-base font-bold text-base-content">{{ __('Financial values') }}</h2>
                    <p class="mt-1 text-xs leading-5 text-base-content/55">
                        {{ __('Financial values stay hidden until you reveal them.') }}
                    </p>
                </div>
                <span class="mt-2 inline-flex w-fit items-center gap-1.5 rounded-full bg-base-200 px-2.5 py-1 text-xs text-base-content/60 sm:mt-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2Zm10-10V7a4 4 0 0 0-8 0v4" />
                    </svg>
                    {{ __('Reveal one value at a time') }}
                </span>
            </div>
            @php
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
                
                $privateMetricCount = $basePrivateMetrics->count() + $rolePrivateMetrics->count();
                $privateMetricGridClass = $privateMetricCount >= 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4';
            @endphp

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 {{ $privateMetricGridClass }}">
                @foreach ($basePrivateMetrics as $metric)
                    @include('home._private-metric-card', compact('metric'))
                @endforeach

                @foreach ($rolePrivateMetrics as $metric)
                    @include('home._private-metric-card', compact('metric'))
                @endforeach
            </div>
        </section>
    @endif
@endcan
