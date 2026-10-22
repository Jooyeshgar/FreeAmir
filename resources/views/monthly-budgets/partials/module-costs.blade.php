<section id="monthly-module-costs" class="budget-panel" aria-labelledby="monthly-module-costs-title">
    <div class="budget-panel-heading">
        <div>
            <h2 id="monthly-module-costs-title" class="font-semibold">{{ __('Module share of total expenses') }}</h2>
            <p class="budget-caption">{{ __('Each amount is compared with total accounting expenses for the selected period.') }}</p>
        </div>
        <div class="text-end">
            <span class="budget-caption">{{ __('Total expenses') }} · {{ $currency }}</span>
            <div class="font-bold tabular-nums">{{ is_null($actualExpense) ? '—' : formatNumber(-$actualExpense) }}</div>
        </div>
    </div>
    <div class="budget-module-grid">
        @foreach (['payroll' => __('Payroll'), 'cogs' => __('Cost of Goods Sold'), 'services' => __('Purchased services')] as $module => $label)
            @php($cost = $moduleCostShares[$module])
            <article class="budget-module" data-module-cost="{{ $module }}">
                <h3 class="text-sm font-semibold">{{ $label }}</h3>
                <div class="budget-module-ratio">{{ is_null($cost['share']) ? '—' : localizeNumber(number_format($cost['share'], 1)).'%' }}</div>
                <div class="text-sm tabular-nums">{{ is_null($cost['amount']) ? '—' : formatNumber($cost['amount']) }} <span class="budget-caption">{{ $currency }}</span></div>
                <div class="budget-share" aria-hidden="true"><span style="width: {{ max(0, min(100, $cost['share'] ?? 0)) }}%"></span></div>
                <p class="budget-caption">{{ $module === 'payroll' ? __('Approved and paid payslips: gross earnings plus employer insurance.') : __('Net approved accounting balance, including returns and adjustments.') }}</p>
                @if (is_null($cost['amount']))
                    <p class="budget-caption">{{ __('No cost accounts are configured for this module.') }}</p>
                @elseif (($cost['share'] ?? 0) > 100)
                    <p class="budget-caption text-warning">{{ __('This module exceeds recorded total expenses; check that its costs are posted in the same period.') }}</p>
                @endif
            </article>
        @endforeach
    </div>
    <div class="border-t border-base-200 px-5 py-3">
        <p class="budget-caption">{{ __('Payroll uses the payslip month; the current month includes payslips issued through today. Payroll amounts may differ from posted expenses. Module percentages are independent and are not added together.') }}</p>
        @if (is_null($actualExpense) || $actualExpense >= 0)
            <p class="budget-caption">{{ __('Cost shares are unavailable until positive total expenses are recorded.') }}</p>
        @endif
    </div>
</section>
