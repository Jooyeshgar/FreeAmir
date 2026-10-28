<div id="monthly-module-costs" class="card min-w-0 overflow-hidden border border-base-300 bg-base-100/90 shadow-sm
    print:shadow-none print:break-inside-avoid grid-cols-3 grid mt-4 pt-1.5
    border-t border-t-base-300 max-md:grid-cols-1" aria-label="{{ __('Module share of total expenses') }}">
    @foreach ([
        'cogs' => [__('Cost of Goods Sold'), __('Share of total expenses')],
        'payroll' => [__('Payroll'), __('Approved payslips in this month')],
        'services' => [__('Purchased services'), __('Approved accounting documents in this month')],
    ] as $module => [$label, $caption])
        @php($cost = $moduleCostShares[$module])
        <article class="flex items-center gap-3 min-w-0 px-5 py-4 [&+&]:border-s [&+&]:border-s-base-300
            [&+&]:max-md:border-s-0 [&+&]:max-md:border-t [&+&]:max-md:border-t-base-300" data-module-cost="{{ $module }}">
            <div class="min-w-0 [&_strong]:block [&_strong]:text-xs [&_small]:block [&_small]:overflow-hidden
                [&_small]:text-base-content/60 [&_small]:text-ellipsis [&_small]:whitespace-nowrap [&_small]:text-xs">
                <strong>{{ $label }}</strong>
                <small>{{ is_null($cost['share']) ? ($module === 'cogs' ? __('No cost accounts are configured for this module.') : $caption) : localizeNumber(number_format($cost['share'], 1)).'% · '.$caption }}</small>
            </div>
            <span class="ms-auto [direction:ltr] text-left text-xs font-bold whitespace-nowrap {{ ['cogs' => 'text-warning', 'payroll' => 'text-primary', 'services' => 'text-secondary'][$module] }}">{{ is_null($cost['amount']) ? '—' : formatNumber($cost['amount']).' '.$currency }}</span>
        </article>
    @endforeach
</div>
