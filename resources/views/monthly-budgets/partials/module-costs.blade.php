<div id="monthly-module-costs" class="budget-card budget-module-strip" aria-label="{{ __('Module share of total expenses') }}">
    @foreach ([
        'cogs' => [__('Cost of Goods Sold'), __('Share of total expenses')],
        'payroll' => [__('Payroll'), __('Approved payslips in this month')],
        'services' => [__('Purchased services'), __('Approved accounting documents in this month')],
    ] as $module => [$label, $caption])
        @php($cost = $moduleCostShares[$module])
        <article class="budget-module" data-module-cost="{{ $module }}">
            <div class="budget-module-copy">
                <strong>{{ $label }}</strong>
                <small>{{ is_null($cost['share']) ? ($module === 'cogs' ? __('No cost accounts are configured for this module.') : $caption) : localizeNumber(number_format($cost['share'], 1)).'% · '.$caption }}</small>
            </div>
            <span class="budget-module-value">{{ is_null($cost['amount']) ? '—' : formatNumber($cost['amount']).' '.$currency }}</span>
        </article>
    @endforeach
</div>
