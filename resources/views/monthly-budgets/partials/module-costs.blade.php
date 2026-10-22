<section id="monthly-module-costs" class="budget-panel" aria-labelledby="monthly-module-costs-title">
    <div class="budget-panel-heading">
        <h2 id="monthly-module-costs-title" class="font-semibold">{{ __('Module share of total expenses') }}</h2>
    </div>
    <div class="budget-module-grid">
        @foreach (['cogs' => __('Cost of Goods Sold'), 'payroll' => __('Payroll'), 'services' => __('Purchased services')] as $module => $label)
            @php($cost = $moduleCostShares[$module])
            <article class="budget-module" data-module-cost="{{ $module }}">
                <div class="budget-module-heading">
                    <h3 class="budget-module-name">{{ $label }} <span class="budget-module-amount">{{ is_null($cost['amount']) ? '—' : formatNumber($cost['amount']).' '.$currency }}</span></h3>
                    <div class="budget-module-ratio">{{ is_null($cost['share']) ? '—' : localizeNumber(number_format($cost['share'], 1)).'%' }}</div>
                </div>
                <div class="budget-share" aria-hidden="true"><span style="width: {{ max(0, min(100, $cost['share'] ?? 0)) }}%"></span></div>
                @if (is_null($cost['amount']))
                    <p class="budget-caption">{{ __('No cost accounts are configured for this module.') }}</p>
                @elseif (($cost['share'] ?? 0) > 100)
                    <p class="budget-caption text-warning">{{ __('This module exceeds recorded total expenses; check that its costs are posted in the same period.') }}</p>
                @endif
            </article>
        @endforeach
    </div>
    <div class="budget-module-note">
        <span class="budget-module-note-icon" aria-hidden="true">i</span>
        {{ __('Payroll amounts may differ from posted expenses. Module percentages are independent.') }}
        @if (is_null($actualExpense) || $actualExpense >= 0)
            {{ __('Cost shares are unavailable until positive total expenses are recorded.') }}
        @endif
    </div>
    <details class="budget-module-details">
        <summary>{{ __('How these figures are calculated') }}</summary>
        <p>{{ __('Each amount is compared with total accounting expenses for the selected period.') }} <strong>{{ __('Total expenses') }}: {{ is_null($actualExpense) ? '—' : formatNumber(-$actualExpense).' '.$currency }}</strong></p>
        <p><strong>{{ __('Payroll') }}:</strong> {{ __('Approved and paid payslips: gross earnings plus employer insurance.') }}</p>
        <p><strong>{{ __('Cost of Goods Sold') }} / {{ __('Purchased services') }}:</strong> {{ __('Net approved accounting balance, including returns and adjustments.') }}</p>
        <p>{{ __('Payroll uses the payslip month; the current month includes payslips issued through today. Payroll amounts may differ from posted expenses. Module percentages are independent and are not added together.') }}</p>
    </details>
</section>
