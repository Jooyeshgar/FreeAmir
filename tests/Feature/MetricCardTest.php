<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetricCardTest extends TestCase
{
    public function test_existing_metric_cards_keep_their_value_change_and_progress(): void
    {
        $this->blade('<x-metric-card title="Income" :value="1200" suffix="Rial" :change="5" :progress="60" detail="Annual total" />')
            ->assertSee('Income')
            ->assertSee(formatNumber(1200))
            ->assertSee('Rial')
            ->assertSee('Annual total')
            ->assertSee('value="60"', false)
            ->assertSee('text-success', false)
            ->assertSee(formatNumber(5));
    }

    public function test_monthly_cards_can_supply_an_unavailable_value_and_favorable_expense_comparison(): void
    {
        $this->blade(<<<'BLADE'
            <x-metric-card title="Expense" value="1234" detail="Default detail">
                <x-slot:valueContent><div dir="ltr">—</div></x-slot:valueContent>
                <p>Monthly context</p>
                <x-slot:footer>
                    @include('monthly-budgets.partials.change', [
                        'comparison' => ['change' => -100, 'percent' => -10],
                        'increaseIsGood' => false,
                    ])
                </x-slot:footer>
            </x-metric-card>
            BLADE)
            ->assertSee('Expense')
            ->assertSee('<div dir="ltr">—</div>', false)
            ->assertSee('Monthly context')
            ->assertSee('data-direction="up"', false)
            ->assertSee('↓')
            ->assertDontSee(formatNumber(1234))
            ->assertDontSee('Default detail');
    }
}
